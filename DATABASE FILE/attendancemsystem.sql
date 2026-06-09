-- Web-Based Student Attendance Management System
-- Generic Edition — works for any school or institution
-- Terms: Program (e.g. BSIT), Section (e.g. BSIT-1A), Faculty, Student Number, Semester

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- CREATE DATABASE IF NOT EXISTS `attendancemsystem` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- USE `attendancemsystem`;

-- --------------------------------------------------------
-- tbladmin
-- --------------------------------------------------------
CREATE TABLE `tbladmin` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(50) NOT NULL,
  `lastName` varchar(50) NOT NULL,
  `emailAddress` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `emailAddress` (`emailAddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: email = admin@school.edu | password = admin123
-- IMPORTANT: Change these credentials after first login!
INSERT INTO `tbladmin` (`firstName`, `lastName`, `emailAddress`, `password`) VALUES
('Admin', '', 'admin@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- --------------------------------------------------------
-- tblclassteacher (Faculty)
-- --------------------------------------------------------
CREATE TABLE `tblclassteacher` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `emailAddress` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phoneNo` varchar(20) NOT NULL,
  `classId` int(10) NOT NULL,
  `classArmId` int(10) NOT NULL,
  `dateCreated` date NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `emailAddress` (`emailAddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default faculty: email = faculty@school.edu | password = faculty123
-- IMPORTANT: Change these credentials after first login!
INSERT INTO `tblclassteacher` (`firstName`, `lastName`, `emailAddress`, `password`, `phoneNo`, `classId`, `classArmId`, `dateCreated`) VALUES
('Juan', 'Dela Cruz', 'faculty@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '09171234567', 1, 1, CURDATE());

-- --------------------------------------------------------
-- tblclass (Program)
-- Add your school's programs/courses here
-- --------------------------------------------------------
CREATE TABLE `tblclass` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `className` varchar(100) NOT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample programs — replace with your school's actual programs
INSERT INTO `tblclass` (`Id`, `className`) VALUES
(1, 'BSIT'),
(2, 'BSCS'),
(3, 'BSA'),
(4, 'BSBA');

-- --------------------------------------------------------
-- tblclassarms (Section)
-- --------------------------------------------------------
CREATE TABLE `tblclassarms` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `classId` int(10) NOT NULL,
  `classArmName` varchar(100) NOT NULL,
  `isAssigned` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id`),
  KEY `classId` (`classId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample sections — each program has consistent 1A and 1B sections
INSERT INTO `tblclassarms` (`Id`, `classId`, `classArmName`, `isAssigned`) VALUES
(1,  1, 'BSIT-1A',  1),
(2,  1, 'BSIT-1B',  1),
(3,  2, 'BSCS-1A',  1),
(4,  2, 'BSCS-1B',  0),
(5,  3, 'BSA-1A',   1),
(6,  3, 'BSA-1B',   0),
(7,  4, 'BSBA-1A',  1),
(8,  4, 'BSBA-1B',  0);

-- --------------------------------------------------------
-- tblsessionterm (Semester / Academic Term)
-- --------------------------------------------------------
CREATE TABLE `tblsessionterm` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `sessionName` varchar(50) NOT NULL,
  `termId` int(10) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 0,
  `dateCreated` date NOT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tblsessionterm` (`sessionName`, `termId`, `isActive`, `dateCreated`) VALUES
('2024-2025', 1, 1, CURDATE()),
('2024-2025', 2, 0, CURDATE());

-- --------------------------------------------------------
-- tblterm (Semester Period)
-- --------------------------------------------------------
CREATE TABLE `tblterm` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `termName` varchar(20) NOT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tblterm` (`termName`) VALUES ('1st Semester'), ('2nd Semester'), ('Summer');

-- --------------------------------------------------------
-- tblstudents
-- --------------------------------------------------------
CREATE TABLE `tblstudents` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `otherName` varchar(100) DEFAULT NULL,
  `admissionNumber` varchar(50) NOT NULL,
  `classId` int(10) NOT NULL,
  `classArmId` int(10) NOT NULL,
  `dateCreated` date NOT NULL,
  PRIMARY KEY (`Id`),
  UNIQUE KEY `admissionNumber` (`admissionNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- No sample students — add your own via the admin panel

-- --------------------------------------------------------
-- tblattendance
-- --------------------------------------------------------
CREATE TABLE `tblattendance` (
  `Id` int(10) NOT NULL AUTO_INCREMENT,
  `admissionNo` varchar(50) NOT NULL,
  `classId` int(10) NOT NULL,
  `classArmId` int(10) NOT NULL,
  `sessionTermId` int(10) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `dateTimeTaken` date NOT NULL,
  PRIMARY KEY (`Id`),
  KEY `admissionNo` (`admissionNo`),
  KEY `classId` (`classId`),
  KEY `dateTimeTaken` (`dateTimeTaken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
