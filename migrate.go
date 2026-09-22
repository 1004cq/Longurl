package main

import "database/sql"

func migrateSchema(db *sql.DB) {
	if db == nil {
		return
	}
	_, _ = db.Exec(`ALTER TABLE links ADD COLUMN created_ip VARCHAR(45) NOT NULL DEFAULT '' AFTER target_url`)
}
