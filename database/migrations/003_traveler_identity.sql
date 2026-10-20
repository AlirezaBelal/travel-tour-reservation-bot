-- Only pseudonymous record links are indexed. Identifiers are encrypted with an operator-held key.
-- Legacy Data.Mobile / Data.N_Code columns remain untouched for backwards compatibility.
CREATE TABLE IF NOT EXISTS TravelerIdentity (
 user_id BIGINT NOT NULL PRIMARY KEY,
 mobile_cipher VARBINARY(256) NULL,
 national_cipher VARBINARY(256) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES Data(UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
