-- Apply once to an existing v2 database that does not yet have these columns.
-- Fresh imports of Dump20261016.sql already include them.
ALTER TABLE blood_banks
  ADD latitude DECIMAL(8,6) NULL,
  ADD longitude DECIMAL(9,6) NULL;
