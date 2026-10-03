-- 001_schema.sql
-- Creates the core tables for users, events, bookings, blog posts, and subscribers.

CREATE TABLE IF NOT EXISTS users (
  userid INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  firstname VARCHAR(255) NOT NULL,
  lastname VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
  eventid INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(255) NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  event_date DATETIME NOT NULL,
  location VARCHAR(255) NOT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bookings (
    bookingid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    eventid INT NOT NULL,
    booked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmation_sent TINYINT(1) NOT NULL DEFAULT 0,
    confirmation_sent_at DATETIME NULL,
    reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
    reminder_sent_at DATETIME NULL,

    FOREIGN KEY (userid) REFERENCES users(userid) ON DELETE CASCADE,
    FOREIGN KEY (eventid) REFERENCES events(eventid) ON DELETE CASCADE,

    UNIQUE KEY unique_booking (userid, eventid)
);

CREATE TABLE blog_posts (
    postid INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS subscribers (
    subscriberid INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ADDED: shared PHP sessions must live outside the Vercel container.
CREATE TABLE IF NOT EXISTS sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    session_data MEDIUMTEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_sessions_expires_at (expires_at)
);