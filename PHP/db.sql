CREATE DATABASE IF NOT EXISTS `jobp_db`;

USE `jobp_db`;

CREATE TABLE IF NOT EXISTS `conversations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user1_id` INT NULL,
    `user2_id` INT NULL,
    `company1_id` INT NULL,
    `company2_id` INT NULL,
    `conversation_type` ENUM('user_to_user', 'user_to_company', 'company_to_company') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company1_id) REFERENCES cuser(id) ON DELETE CASCADE,
    FOREIGN KEY (company2_id) REFERENCES cuser(id) ON DELETE CASCADE,
    INDEX `conv_type_user1_user2` (conversation_type, user1_id, user2_id),
    INDEX `conv_type_company1_company2` (conversation_type, company1_id, company2_id),
    INDEX `conv_type_user_company` (conversation_type, user1_id, company1_id)
);

-- Messages table remains mostly the same
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `sender_type` ENUM('user', 'company') NOT NULL,
    `sender_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_read` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX `conversation_index` (conversation_id, timestamp),
    INDEX `sender_index` (sender_type, sender_id)
);


SET SQL_SAFE_UPDATES = 0;

DELETE FROM conversations
WHERE user1_id = 1;

SET SQL_SAFE_UPDATES = 1;


select * from messages;
select * from conversations;


CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `company_id` INT NULL,
    `user_type` ENUM('user', 'company') NOT NULL,
    `content` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    -- Note: We remove foreign key constraints to avoid issues with same IDs
    -- This is necessary if IDs can be the same across different tables
);

CREATE TABLE IF NOT EXISTS `post_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`)
);

CREATE TABLE IF NOT EXISTS `post_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `user_id` INT NULL,
    `company_id` INT NULL,
    `user_type` ENUM('user', 'company') NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
);









CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `country_code` VARCHAR(10) NOT NULL,
    `phone_number` VARCHAR(20) NOT NULL UNIQUE,
    `education` VARCHAR(255) NULL,
    `work_experience` VARCHAR(255) NULL,
    `is_verified` BOOLEAN DEFAULT FALSE,
    `job_field` VARCHAR(100) NULL,
    `employment_type` VARCHAR(50) NULL,
	`shift_type` VARCHAR(50) NULL, 
	`part_time_hours` VARCHAR(50) NULL,
    `profile_url` VARCHAR(255) DEFAULT 'https://i.ibb.co/sJsj4h5X/c229ebb16cd9.jpg' ,
    `address` VARCHAR(255),
    `skills` VARCHAR(255),
    `language` VARCHAR(255),
    `bio` VARCHAR(255),
    `age` varchar(255),
	`gender` VARCHAR(255),
    `test_pass` VARCHAR(255) NULL, -- New column added here
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    -- `user_id` VARCHAR(255)
);
ALTER TABLE `users`
MODIFY `gender` varchar(255) null;
-- ADD COLUMN `gender` VARCHAR(255);
    

select * from `users`;

create table cuser(
`id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `country_code` VARCHAR(10) NOT NULL,
    `phone_number` VARCHAR(20) NOT NULL UNIQUE,
    `is_verified` BOOLEAN DEFAULT FALSE,
    `cverified` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `profile_photo` VARCHAR(255) DEFAULT 'https://i.ibb.co/sJsj4h5X/c229ebb16cd9.jpg',
    `headquarter` VARCHAR(255),
    `industry` VARCHAR(255),
    `company_type` VARCHAR(255),
    website text,
       overview text,
       founded_year VARCHAR(255)
       
);
create table Jobs (id int auto_increment primary key,
					cuser_id varchar(255) ,
                    job_title varchar(255) ,
                    job_desc text,
                    location text,
                    work_mode varchar(255),
                    experience varchar(255),
                    time varchar(255),
                    salary varchar(255),
                    skills varchar (255),
					form_link varchar(255)

);
select * from cuser;

select * from users;
CREATE TABLE companies(
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuser_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    business_type VARCHAR(50),
    legal_status VARCHAR(100),
    registration_number VARCHAR(100),
    date_established DATE,
    physical_address TEXT,
    contact_person VARCHAR(255),
    contact_title VARCHAR(100),
    phone_number VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(255),
    nature_of_business TEXT,
    products_services TEXT,
    hours_of_operation VARCHAR(255),
    num_employees INT,
    documents TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE companies ADD COLUMN status VARCHAR(20) DEFAULT 'pending';





select * from companies;

create table experience(
`id` INT AUTO_INCREMENT PRIMARY KEY,
`userId` varchar(255) not null,
`company_name` varchar(255) not null,
`postion` varchar(255) not null,
`date` varchar (255)not null
);

create table education(
`id` INT AUTO_INCREMENT PRIMARY KEY,
`userId` varchar(255) not null,
`instude_name` varchar(255) not null,
`class` varchar(255) not null,
`years` varchar (255)not null,
`percentage` varchar (255)not null
);

create table admin(
userid int auto_increment primary key,
email varchar(255),
password varchar(255)
);
INSERT INTO admin (email, password)
VALUES ("adarshmaurya8383@gmail.com", "jobkaro@adarsh"),
("hrushita.mane@gmail.com", "jobkaro@hrushita"),
("patilsania0811@gmail.com", "jobkaro@sania"),
("shuklanandlal2@gmail.com", "jobkaro@nandlal"),
("shumailaakhan01@gmail.com", "jobkaro@shumaila");




update `users`
set `test_pass` = "passed"
where id = 3;



show tables from lab;
use lab;
ALTER TABLE books_issued
ADD COLUMN issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

select * from books_issued;
ALTER TABLE books_issued ADD COLUMN return_date DATETIME;
ALTER TABLE users ADD COLUMN user_id VARCHAR(255);
ALTER TABLE cuser ADD COLUMN headquarter VARCHAR(255);
ALTER TABLE cuser ADD COLUMN industry VARCHAR(255);
ALTER TABLE cuser ADD COLUMN company_type VARCHAR(255);
ALTER TABLE cuser ADD COLUMN overview text;


ALTER TABLE users
DROP COLUMN cuser_id;
