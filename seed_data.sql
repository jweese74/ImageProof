-- seed_data.sql: Deterministic test data for ImageProof (Phase 1)
-- Inserts one user and one image to demonstrate foreign key integrity.

INSERT INTO users (id, email, hashed_password)
VALUES (1, 'testuser@example.com', '$2b$12$d0V5m6WmIul1gUHXqYOfH.uNar5dBVK0L37tVgW0z2Jl2J2yJ4j8W');

INSERT INTO images (id, user_id, sha256, phash)
VALUES (1, 1, '0e5751c026e543b2e8ab2eb06099eda2f4a2833f8b3e0b675d18497ad5e6eead', 'ffbbaaaaffbbaaaa');
