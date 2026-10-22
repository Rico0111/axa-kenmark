-- Adds a session generation counter to administrator accounts.
-- Incrementing the value invalidates existing sessions for that account after a
-- password reset or deactivation. The counter itself is never a secret.

ALTER TABLE admins
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER locked_until;
