-- SQLite doesn't require database creation and USE statements

-- Create departments table
CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);

-- Create employees table
CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    department_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Create attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    check_type TEXT CHECK(check_type IN ('check_in', 'check_out')) NOT NULL,
    photo_path TEXT NOT NULL,
    ip_address TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date DATE NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Create admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (username: admin, password: admin123)
-- This hash is for 'admin123' using PASSWORD_DEFAULT
INSERT INTO admin_users (username, password) 
VALUES ('admin', '$2y$10$3HF3SYuV7t/B5ZKnbJGxMe/Nr5EWZwJwMT6C6H1s/Dl1zDyPc0Wy6');

-- Insert some default departments
INSERT INTO departments (name) VALUES 
('Human Resources'),
('Information Technology'),
('Finance'),
('Marketing'),
('Operations');
