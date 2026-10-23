"""Hostinger 2 only. SFTP subsystem only; no shell, delete, or remote extraction."""
import hashlib
import json
import logging
import os
from pathlib import Path, PurePosixPath
import re
import stat
import sys
import tempfile
import zipfile

import paramiko

# Keep library diagnostics from echoing connection metadata supplied as secrets.
logging.getLogger('paramiko').addHandler(logging.NullHandler())
logging.getLogger('paramiko').propagate = False

ROOT = '/home/u569245175/domains/techtalkmnl.com/public_html/axa-kenmark'
STAGING = '/home/u569245175/axa-kenmark-deploy-staging'
MARKER = '.axa-deploy-target'
IDENTITY = b'axa-kenmark-hostinger-2\n'
PACKAGE = Path('dist/hostinger')
TOP = {'app', 'config', 'database', 'docs', 'public', 'scripts', 'storage', 'tests'}
ROOT_FILES = {'.htaccess', 'index.php', '.env.example', '.env.production.example', 'README.md', 'README-HOSTINGER.md'}
UNSAFE = re.compile(r'\.(?:log|bak|backup|dump|zip|gz|7z|tar|rar|tmp|swp|pem|key|p12|pfx|cookie|cookies|session)$|~$', re.I)


def safe_name(name):
    parts = PurePosixPath(name).parts
    if not parts or name != '/'.join(parts) or name.startswith('/') or '\\' in name:
        return False
    if any(p in {'.', '..', 'uploads', 'backups', 'dumps', 'vendor', 'node_modules', 'private'} for p in parts):
        return False
    if any(p.startswith('.') and p != '.htaccess' for p in parts):
        return name in ROOT_FILES
    if UNSAFE.search(name) or (name.endswith('.sql') and not name.startswith('database/migrations/')):
        return False
    return name in ROOT_FILES or (len(parts) > 1 and parts[0] in TOP and
                                 (parts[0] != 'storage' or name == 'storage/.htaccess'))


def release():
    manifest = json.loads((PACKAGE / 'localhost-copy-manifest.json').read_text())
    files = {}
    with zipfile.ZipFile(PACKAGE / 'axa-kenmark-localhost-copy.zip') as archive:
        names = archive.namelist()
        if len(names) != len(set(names)):
            raise ValueError('Duplicate ZIP paths')
        for info in archive.infolist():
            if info.is_dir():
                continue
            name = info.filename
            if not safe_name(name) or stat.S_ISLNK(info.external_attr >> 16):
                raise ValueError('Unsafe release entry')
            data = archive.read(info)
            if hashlib.sha256(data).hexdigest() != manifest.get(name):
                raise ValueError('Release hash mismatch')
            files[name] = data
    if set(files) != set(manifest) or not {'.htaccess', 'index.php', 'storage/.htaccess'} <= files.keys():
        raise ValueError('Incomplete release')
    print(f'Audited {len(files)} credential-free source entries.')
    return files


def existing(sftp, path):
    try:
        return sftp.lstat(path)
    except FileNotFoundError:
        return None


def directory(sftp, path, create=False):
    """Reject every symlink ancestor, including redirects outside this account."""
    current = ''
    for part in PurePosixPath(path).parts[1:]:
        current += '/' + part
        attr = existing(sftp, current)
        if attr is None and create:
            sftp.mkdir(current, mode=0o755)
            attr = sftp.lstat(current)
        if attr is None or not stat.S_ISDIR(attr.st_mode):
            raise ValueError('Missing directory or symlink in destination')
        if sftp.normalize(current) != current:
            raise ValueError('Destination canonical path differs')


def regular(sftp, path):
    attr = existing(sftp, path)
    if attr is not None and not stat.S_ISREG(attr.st_mode):
        raise ValueError('Remote file is a symlink or special file')
    return attr


def read(sftp, path):
    regular(sftp, path)
    with sftp.open(path, 'rb') as stream:
        return stream.read()


def target(sftp, mode, run_id, files):
    if mode not in {'staging', 'production'} or not re.fullmatch(r'[0-9]+-[0-9]+', run_id):
        raise ValueError('Invalid deployment mode or run identity')
    base = ROOT if mode == 'production' else STAGING
    directory(sftp, base)
    if read(sftp, base + '/' + MARKER) != IDENTITY:
        raise ValueError('Hostinger 2 destination marker mismatch')
    if mode == 'production':
        if regular(sftp, ROOT + '/.env') is None:
            raise ValueError('Production .env must be provisioned separately')
        directory(sftp, ROOT + '/storage')
        if read(sftp, ROOT + '/storage/.htaccess') != files['storage/.htaccess']:
            raise ValueError('Persistent storage protection needs operator review')
        return ROOT
    destination = STAGING + '/' + run_id
    if existing(sftp, destination) is not None:
        raise ValueError('Staging run directory already exists; use a new run attempt')
    sftp.mkdir(destination, mode=0o700)
    directory(sftp, destination)
    return destination


def upload(sftp, destination, files, production, run_id):
    if destination != ROOT and not re.fullmatch(re.escape(STAGING) + r'/[0-9]+-[0-9]+', destination):
        raise ValueError('Unapproved destination')
    if production != (destination == ROOT):
        raise ValueError('Mode and destination differ')
    selected = {name: data for name, data in files.items()
                if not (production and name.startswith('storage/'))}
    # Preflight EVERY existing path before uploading anything. No symlinks followed.
    for name in selected:
        path = destination + '/' + name
        directory(sftp, str(PurePosixPath(path).parent), create=True)
        regular(sftp, path)
        if existing(sftp, path + '.axa-deploy-' + run_id) is not None:
            raise ValueError('Unexpected temporary deployment file')
    # Install deny rules first. Each rename is atomic; the whole release is not.
    ordered = sorted(selected, key=lambda n: (not n.endswith('.htaccess'), n == 'index.php', n))
    for name in ordered:
        path = destination + '/' + name
        directory(sftp, str(PurePosixPath(path).parent))
        regular(sftp, path)
        temporary = path + '.axa-deploy-' + run_id
        data = selected[name]
        # Exclusive creation prevents a pre-existing temporary symlink being followed.
        with sftp.open(temporary, 'wx') as stream:
            stream.write(data)
        sftp.chmod(temporary, 0o644)
        if hashlib.sha256(read(sftp, temporary)).digest() != hashlib.sha256(data).digest():
            raise ValueError('Remote temporary hash mismatch')
        sftp.posix_rename(temporary, path)
    for name, data in selected.items():
        if hashlib.sha256(read(sftp, destination + '/' + name)).digest() != hashlib.sha256(data).digest():
            raise ValueError('Post-transfer remote hash mismatch')
    print(f'Verified {len(selected)} remote files. Persistent storage preserved; no remote deletions.')


def transfer(files):
    keys = ['HOSTINGER_SFTP_HOST', 'HOSTINGER_SFTP_PORT', 'HOSTINGER_SFTP_USER',
            'HOSTINGER_SFTP_PASSWORD', 'HOSTINGER_SSH_KNOWN_HOSTS']
    if any(not os.environ.get(key, '').strip() for key in keys):
        raise ValueError('A required Hostinger SFTP secret is empty')
    port = int(os.environ[keys[1]])
    if not 1 <= port <= 65535:
        raise ValueError('Invalid SFTP port')
    mode = os.environ['DEPLOY_MODE']
    run_id = os.environ['GITHUB_RUN_ID'] + '-' + os.environ['GITHUB_RUN_ATTEMPT']
    with tempfile.TemporaryDirectory() as folder:
        known = Path(folder) / 'known_hosts'
        known.write_text(os.environ[keys[4]].strip() + '\n')
        known.chmod(0o600)
        with paramiko.SSHClient() as client:
            client.load_host_keys(str(known))
            client.set_missing_host_key_policy(paramiko.RejectPolicy())
            client.connect(hostname=os.environ[keys[0]], port=port,
                           username=os.environ[keys[2]], password=os.environ[keys[3]],
                           look_for_keys=False, allow_agent=False, timeout=30,
                           banner_timeout=30, auth_timeout=30)
            with client.open_sftp() as sftp:
                sftp.get_channel().settimeout(60)
                destination = target(sftp, mode, run_id, files)
                upload(sftp, destination, files, mode == 'production', run_id)


if __name__ == '__main__':
    try:
        files = release()
        if sys.argv[1:] == ['transfer']:
            transfer(files)
        elif sys.argv[1:] != ['audit']:
            raise ValueError('Expected audit or transfer')
    except Exception as error:
        # Do not echo exceptions from authentication/network libraries (may contain secrets).
        print(f'Deployment stopped ({type(error).__name__}); inspect configuration and runbook.', file=sys.stderr)
        sys.exit(1)
