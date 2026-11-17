CREATE DATABASE IF NOT EXISTS wordpress_tests;
CREATE USER IF NOT EXISTS 'wordpress_tests'@'%' IDENTIFIED BY 'local-tests';
GRANT ALL PRIVILEGES ON wordpress_tests.* TO 'wordpress_tests'@'%';
