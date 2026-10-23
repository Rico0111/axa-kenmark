import io
from pathlib import PurePosixPath
import stat
from types import SimpleNamespace
import unittest

import sftp_release as deploy


class FakeSFTP:
    """In-memory server: records all mutations and models symlinks explicitly."""
    def __init__(self):
        self.nodes = {}
        self.writes = []
        for base in [deploy.ROOT, deploy.STAGING]:
            current = ''
            for part in PurePosixPath(base).parts[1:]:
                current += '/' + part
                self.nodes[current] = (stat.S_IFDIR, b'')
            self.nodes[base + '/' + deploy.MARKER] = (stat.S_IFREG, deploy.IDENTITY)
        self.nodes[deploy.ROOT + '/.env'] = (stat.S_IFREG, b'production-secret')
        self.nodes[deploy.ROOT + '/storage'] = (stat.S_IFDIR, b'')
        self.nodes[deploy.ROOT + '/storage/.htaccess'] = (stat.S_IFREG, b'deny')
        self.nodes[deploy.ROOT + '/storage/live-upload'] = (stat.S_IFREG, b'customer-data')

    def lstat(self, path):
        if path not in self.nodes:
            raise FileNotFoundError(path)
        return SimpleNamespace(st_mode=self.nodes[path][0])

    def normalize(self, path):
        return path

    def mkdir(self, path, mode):
        self.nodes[path] = (stat.S_IFDIR, b'')

    def open(self, path, mode):
        if mode == 'rb':
            return io.BytesIO(self.nodes[path][1])
        assert mode == 'wx' and path not in self.nodes
        server = self

        class Writer(io.BytesIO):
            def close(self):
                if not self.closed:
                    server.nodes[path] = (stat.S_IFREG, self.getvalue())
                    server.writes.append(path)
                super().close()

        return Writer()

    def chmod(self, path, mode):
        pass

    def posix_rename(self, source, destination):
        self.nodes[destination] = self.nodes.pop(source)


class TransferTests(unittest.TestCase):
    def setUp(self):
        self.sftp = FakeSFTP()
        self.files = {'.htaccess': b'root-deny', 'storage/.htaccess': b'deny',
                      'app/bootstrap.php': b'code', 'index.php': b'entry'}

    def test_production_preserves_environment_storage_and_unowned_files(self):
        old = dict(self.sftp.nodes)
        destination = deploy.target(self.sftp, 'production', '12-1', self.files)
        deploy.upload(self.sftp, destination, self.files, True, '12-1')
        for name in ['/.env', '/storage/.htaccess', '/storage/live-upload']:
            self.assertEqual(old[deploy.ROOT + name], self.sftp.nodes[deploy.ROOT + name])
        self.assertTrue(all(path.startswith(deploy.ROOT + '/') for path in self.sftp.writes))

    def test_staging_writes_only_outside_web_root(self):
        before = {k: v for k, v in self.sftp.nodes.items() if k.startswith(deploy.ROOT)}
        destination = deploy.target(self.sftp, 'staging', '12-1', self.files)
        deploy.upload(self.sftp, destination, self.files, False, '12-1')
        self.assertEqual(before, {k: v for k, v in self.sftp.nodes.items() if k.startswith(deploy.ROOT)})
        with self.assertRaises(ValueError):
            deploy.target(self.sftp, 'staging', '12-1', self.files)

    def test_bad_markers_and_missing_production_configuration_stop(self):
        for name in [deploy.MARKER, '.env', 'storage/.htaccess']:
            with self.subTest(name=name):
                server = FakeSFTP()
                server.nodes.pop(deploy.ROOT + '/' + name)
                with self.assertRaises((ValueError, KeyError)):
                    deploy.target(server, 'production', '12-1', self.files)
                self.assertEqual(server.writes, [])

    def test_symlink_ancestors_and_leaf_stop_before_file_upload(self):
        for name in ['/home/u569245175', deploy.ROOT + '/app', deploy.ROOT + '/index.php']:
            with self.subTest(name=name):
                server = FakeSFTP()
                server.nodes[name] = (stat.S_IFLNK, b'/other-site')
                with self.assertRaises(ValueError):
                    deploy.upload(server, deploy.ROOT, self.files, True, '12-1')
                self.assertEqual(server.writes, [])

    def test_canonical_path_mismatch_stops(self):
        self.sftp.normalize = lambda path: '/other-site'
        with self.assertRaises(ValueError):
            deploy.target(self.sftp, 'production', '12-1', self.files)

    def test_main_website_and_mode_mismatch_rejected(self):
        for path, mode in [(str(PurePosixPath(deploy.ROOT).parent), True),
                           (deploy.ROOT + '-other', True), (deploy.ROOT, False)]:
            with self.assertRaises(ValueError):
                deploy.upload(self.sftp, path, self.files, mode, '12-1')

    def test_archive_path_and_private_file_policy(self):
        for name in ['../index.php', '/index.php', 'app/../index.php', 'app//secret',
                     '.env', 'storage/logs/app.log', 'public/uploads/photo.jpg',
                     'app/key.pem', 'database/export.sql', 'docs/archive.tar', '.git/config']:
            with self.subTest(name=name):
                self.assertFalse(deploy.safe_name(name))
        for name in ['.htaccess', '.env.production.example', 'app/bootstrap.php',
                     'database/migrations/001_initial_schema.sql', 'storage/.htaccess']:
            self.assertTrue(deploy.safe_name(name))


if __name__ == '__main__':
    unittest.main()
