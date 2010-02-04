-- phpMyAdmin SQL Dump
-- version 2.9.1.1-Debian-7
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Sep 24, 2008 at 11:54 AM
-- Server version: 5.0.32
-- PHP Version: 5.2.0-8+etch11
-- 
-- Database: `scas`
-- 

-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `scas`;
USE scas;

-- 
-- Table structure for table `insurge_index`
-- 

CREATE TABLE IF NOT EXISTS `insurge_index` (
  `bnum` int(12) NOT NULL,
  `rating_idx` int(8) NOT NULL default '0',
  `tag_idx` text,
  `review_idx` text,
  PRIMARY KEY  (`bnum`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `insurge_ratings`
-- 

CREATE TABLE IF NOT EXISTS `insurge_ratings` (
  `rate_id` int(12) NOT NULL,
  `repos_id` char(24) default NULL,
  `group_id` char(12) default NULL,
  `uid` varchar(12) default NULL,
  `bnum` int(12) NOT NULL,
  `rating` float NOT NULL,
  `rate_date` datetime NOT NULL,
  PRIMARY KEY  (`rate_id`),
  KEY `repos_id` (`repos_id`),
  KEY `uid` (`uid`),
  KEY `bnum` (`bnum`),
  KEY `rating` (`rating`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `insurge_reviews`
-- 

CREATE TABLE IF NOT EXISTS `insurge_reviews` (
  `rev_id` int(12) NOT NULL,
  `repos_id` char(24) default NULL,
  `group_id` char(12) default NULL,
  `uid` varchar(12) default NULL,
  `bnum` int(12) NOT NULL,
  `rev_title` char(254) NOT NULL,
  `rev_body` text NOT NULL,
  `rev_last_update` timestamp NOT NULL default '0000-00-00 00:00:00' on update CURRENT_TIMESTAMP,
  `rev_create_date` datetime NOT NULL,
  PRIMARY KEY  (`rev_id`),
  KEY `uid` (`uid`),
  KEY `bnum` (`bnum`),
  KEY `repos_id` (`repos_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `insurge_tags`
-- 

CREATE TABLE IF NOT EXISTS `insurge_tags` (
  `tid` int(12) NOT NULL,
  `repos_id` char(24) default NULL,
  `group_id` char(12) default NULL,
  `uid` varchar(12) default NULL,
  `bnum` int(12) NOT NULL,
  `tag` char(254) NOT NULL,
  `tag_date` datetime NOT NULL,
  PRIMARY KEY  (`tid`),
  KEY `repos_id` (`repos_id`),
  KEY `uid` (`uid`),
  KEY `bnum` (`bnum`),
  KEY `tag` (`tag`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
