-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 02, 2026 at 02:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tms_g7`
--

-- --------------------------------------------------------

--
-- Table structure for table `account`
--

CREATE TABLE `account` (
  `AccountID` int(11) NOT NULL,
  `EmployeeID` int(11) DEFAULT NULL,
  `RoleID` int(11) DEFAULT NULL,
  `Username` varchar(50) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `Status` enum('Active','Inactive','Locked') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account`
--

INSERT INTO `account` (`AccountID`, `EmployeeID`, `RoleID`, `Username`, `PasswordHash`, `Status`) VALUES
(1, 1, 1, 'admin', '$2y$10$ZWXh/jFGIGZyhlvpI1aByO2grCDFZI.twIM2oCh.PdshwUbWoxhJ.', 'Active'),
(2, 2, 2, 'manager', '$2y$10$raJegzdjsUfm2KxQMRFq3.fuIOaMv3JMylHANqV3rBUWHpWEyZ93i', 'Active'),
(3, 3, 3, 'accountant', '$2y$10$cT2Cs2xYLQ3nTGavYeKisupaiRccAsEKhh0OhQf3MWncIUkdepH/S', 'Active'),
(4, 4, 4, 'ops', '$2y$10$YRjV3wxVdl/Dutz2u8wRtuLb.kO6PdN6dKlHY266P7vBpzikjDoUi', 'Active'),
(5, 5, 2, 'mgr_david', '$2y$10$asdfghjklqwertyuiopzxc', 'Active'),
(6, 6, 4, 'ops_ahmed', '$2y$10$poiuytrewqasdfghjklmnb', 'Active'),
(7, 7, 4, 'ops_wang', '$2y$10$lkjhgfdsaqwertyuiopmnb', 'Active'),
(8, 8, 2, 'mgr_jan', '$2y$10$mnbvcxzlkjhgfdsapoiuyt', 'Active'),
(9, 9, 4, 'ops_cuong', '$2y$10$qazwsxedcrfvtgbyhnujmi', 'Active'),
(10, 10, 4, 'ops_dung', '$2y$10$okmijnuhbygvtfcrdxeszq', 'Active'),
(11, 11, 4, 'ops_yuki', '$2y$10$plmoknijbuhvygctfxrdze', 'Active'),
(12, 12, 3, 'acc_emma', '$2y$10$zaqxswcdevfrbgtnhymjuk', 'Active'),
(13, 13, 4, 'ops_james', '$2y$10$xswcdevfrbgtnhymjukilp', 'Active'),
(14, 14, 2, 'mgr_kevin', '$2y$10$vfrbgtnhymjukilpoqazws', 'Active'),
(15, 15, 4, 'ops_jisung', '$2y$10$nhymjukilpoqazwsxedcrf', 'Active'),
(16, 16, 4, 'ops_raj', '$2y$10$ilpoqazwsxedcrfvtgbyhn', 'Active'),
(17, 17, 4, 'ops_giang', '$2y$10$qazxswedcvfrtgbnhyujmk', 'Active'),
(18, 18, 4, 'ops_hai', '$2y$10$plmkonjibuhvygctfxrdze', 'Locked'),
(19, 19, 2, 'mgr_john', '$2y$10$zaq12wsxcde34rfvbgt56y', 'Active'),
(20, 20, 4, 'ops_anna', '$2y$10$xsw23edcvfr45tgbnhy67u', 'Inactive');

-- --------------------------------------------------------

--
-- Table structure for table `billing_structure`
--

CREATE TABLE `billing_structure` (
  `BillingID` int(11) NOT NULL,
  `ChargeType` varchar(50) DEFAULT NULL,
  `RateValue` decimal(15,2) DEFAULT NULL,
  `Currency` varchar(10) DEFAULT 'USD',
  `Status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_structure`
--

INSERT INTO `billing_structure` (`BillingID`, `ChargeType`, `RateValue`, `Currency`, `Status`) VALUES
(1, 'Per FEU Container (40ft)', 3500.00, 'USD', 'Active'),
(2, 'Per TEU Container (20ft)', 1800.00, 'USD', 'Active'),
(3, 'Air Freight Per KG', 5.50, 'USD', 'Active'),
(4, 'Road Freight Per Trip (Long)', 1200.00, 'USD', 'Active'),
(5, 'Road Freight Per Trip (Short)', 300.00, 'USD', 'Active'),
(6, 'Last Mile Delivery (Flat)', 15.00, 'USD', 'Active'),
(7, 'Customs Clearance Fee', 150.00, 'USD', 'Active'),
(8, 'Documentation Fee', 50.00, 'USD', 'Active'),
(9, 'Terminal Handling Charge (THC)', 200.00, 'USD', 'Active'),
(10, 'Bunker Adjustment Factor (BAF)', 300.00, 'USD', 'Active'),
(11, 'Peak Season Surcharge (PSS)', 500.00, 'USD', 'Active'),
(12, 'Fuel Surcharge (Air)', 1.20, 'USD', 'Active'),
(13, 'Security Charge (Air)', 0.50, 'USD', 'Active'),
(14, 'Storage Per Pallet Per Day', 2.00, 'USD', 'Active'),
(15, 'Handling In/Out Per Pallet', 5.00, 'USD', 'Active'),
(16, 'Insurance (Flat Rate)', 100.00, 'USD', 'Active'),
(17, 'Heavy Lift Surcharge', 800.00, 'USD', 'Active'),
(18, 'Hazardous Material Surcharge', 1000.00, 'USD', 'Active'),
(19, 'Port Congestion Surcharge', 400.00, 'USD', 'Active'),
(20, 'Weekend Delivery Surcharge', 100.00, 'USD', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `business_party`
--

CREATE TABLE `business_party` (
  `PartyID` int(11) NOT NULL,
  `PartyType` enum('Customer','Carrier') NOT NULL,
  `PartyName` varchar(255) NOT NULL,
  `Address` text DEFAULT NULL,
  `ContactEmail` varchar(100) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_party`
--

INSERT INTO `business_party` (`PartyID`, `PartyType`, `PartyName`, `Address`, `ContactEmail`, `Phone`) VALUES
(1, 'Customer', 'Samsung Electronics', '101 Samsung Blvd, Yen Phong, Bac Ninh, Vietnam', 'sc@samsung.com', '+842223691111'),
(2, 'Customer', 'Apple Inc', '1 Apple Park Way, Cupertino, CA 95014, USA', 'supply@apple.com', '+14089961010'),
(3, 'Customer', 'Sony Corporation', '1-7-1 Konan, Minato-ku, Tokyo 108-0075, Japan', 'logistics@sony.jp', '+81367482111'),
(4, 'Customer', 'Dell Technologies', '1 Dell Way, Round Rock, TX 78682, USA', 'freight@dell.com', '+18002893355'),
(5, 'Customer', 'HP Enterprise', '1701 E Mossy Oaks Rd, Spring, TX 77389, USA', 'shipping@hpe.com', '+16508571501'),
(6, 'Customer', 'Walmart Global', '702 SW 8th St, Bentonville, AR 72716, USA', 'import@walmart.com', '+18009256278'),
(7, 'Customer', 'Target Corp', '1000 Nicollet Mall, Minneapolis, MN 55403, USA', 'logistics@target.com', '+16123046073'),
(8, 'Customer', 'Minh Tuan Mobile', '43 Tran Quang Khai, Dist 1, HCMC, Vietnam', 'contact@minhtuan.vn', '+84901123456'),
(9, 'Customer', 'Hoang Ha Mobile', '122 Thai Ha, Dong Da, Hanoi, Vietnam', 'cskh@hoangha.vn', '+84902233444'),
(10, 'Customer', 'Zara Retail', 'Edificio Inditex, Arteixo, A Coruña 15146, Spain', 'supply@zara.com', '+34981185400'),
(11, 'Customer', 'H&M Group', 'Mäster Samuelsgatan 46A, Stockholm 10638, Sweden', 'logistic@hm.com', '+4687965500'),
(12, 'Customer', 'Nike Inc', 'One Bowerman Dr, Beaverton, OR 97005, USA', 'freight@nike.com', '+15036716453'),
(13, 'Customer', 'Adidas AG', 'Adi-Dassler-Strasse 1, Herzogenaurach 91074, Germany', 'sc@adidas.com', '+499132840'),
(14, 'Customer', 'Unilever PLC', '100 Victoria Embankment, London EC4Y 0DY, UK', 'transport@unilever.com', '+442078225252'),
(15, 'Customer', 'Procter & Gamble', '1 Procter & Gamble Plaza, Cincinnati, OH 45202, USA', 'import@pg.com', '+15139831100'),
(16, 'Customer', 'Nestle SA', 'Avenue Nestle 55, Vevey 1800, Switzerland', 'supply@nestle.com', '+41219241111'),
(17, 'Customer', 'PepsiCo Inc', '700 Anderson Hill Rd, Purchase, NY 10577, USA', 'freight@pepsico.com', '+19142532000'),
(18, 'Customer', 'Coca-Cola Co', '1 Coca-Cola Plaza, Atlanta, GA 30313, USA', 'logistics@coca-cola.com', '+18004382653'),
(19, 'Customer', 'Hoa Phat Group', '66 Nguyen Du, Hai Ba Trung, Hanoi, Vietnam', 'export@hoaphat.com.vn', '+842439747752'),
(20, 'Customer', 'Vinamilk', '10 Tan Trao, Dist 7, HCMC, Vietnam', 'supply@vinamilk.com.vn', '+842854155555'),
(21, 'Carrier', 'Maersk Line', 'Esplanaden 50, 1098 Copenhagen, Denmark', 'booking@maersk.com', '+4533633363'),
(22, 'Carrier', 'MSC', '12-14 Chemin Rieu, 1208 Geneva, Switzerland', 'info@msc.com', '+41227038888'),
(23, 'Carrier', 'CMA CGM', '4 Quai d\'Arenc, 13002 Marseille, France', 'contact@cma-cgm.com', '+33488919000'),
(24, 'Carrier', 'Hapag-Lloyd', 'Ballindamm 25, 20095 Hamburg, Germany', 'booking@hlag.com', '+494030010'),
(25, 'Carrier', 'COSCO Shipping', 'No. 528 Dong Da Ming Road, Shanghai, China', 'info@coscoshipping.com', '+862165966666'),
(26, 'Carrier', 'ONE (Ocean Network)', '1 Straits Boulevard, #16-01, Singapore 018906', 'info@one-line.com', '+6562208801'),
(27, 'Carrier', 'Evergreen Marine', '163 Sec. 1, Nankan Rd., Taoyuan 33858, Taiwan', 'booking@evergreen.com', '+88633222135'),
(28, 'Carrier', 'FedEx Express', '942 S Shady Grove Rd, Memphis, TN 38120, USA', 'info@fedex.com', '+18004633339'),
(29, 'Carrier', 'UPS Supply Chain', '55 Glenlake Pkwy NE, Atlanta, GA 30328, USA', 'freight@ups.com', '+18007425877'),
(30, 'Carrier', 'DHL Global Forwarding', 'Charles-de-Gaulle-Str. 20, 53113 Bonn, Germany', 'info@dhl.com', '+492281820'),
(31, 'Carrier', 'Emirates SkyCargo', 'Emirates Group HQ, PO Box 686, Dubai, UAE', 'cargo@emirates.com', '+97142864040'),
(32, 'Carrier', 'Cathay Pacific Cargo', '8 Scenic Road, HKIA, Lantau, Hong Kong', 'freight@cathay.com', '+85227477888'),
(33, 'Carrier', 'Qatar Airways Cargo', 'Qatar Airways Tower 1, PO Box 22550, Doha, Qatar', 'cargo@qatarairways.com', '+97440226000'),
(34, 'Carrier', 'Kuehne+Nagel', 'Dorfstrasse 50, 8834 Schindellegi, Switzerland', 'info@kuehne-nagel.com', '+41447869511'),
(35, 'Carrier', 'DB Schenker', 'Kruppstrasse 4, 45128 Essen, Germany', 'info@dbschenker.com', '+4920187810'),
(36, 'Carrier', 'Viettel Post', '1 Giang Van Minh, Ba Dinh, Hanoi, Vietnam', 'cskh@viettelpost.com', '+8419008095'),
(37, 'Carrier', 'Vietnam Post', '5 Pham Hung, Nam Tu Liem, Hanoi, Vietnam', 'customercare@vnpost.vn', '+841900545481'),
(38, 'Carrier', 'Giao Hang Nhanh', '405/15 Xo Viet Nghe Tinh, Binh Thanh, HCMC, Vietnam', 'cskh@ghn.vn', '+8418001201'),
(39, 'Carrier', 'J&T Express VN', '10 Mai Chi Tho, Thu Duc, HCMC, Vietnam', 'support@jtexpress.vn', '+8419001088'),
(40, 'Carrier', 'Ninja Van VN', '364 Cong Hoa, Tan Binh, HCMC, Vietnam', 'support_vn@ninjavan.co', '+841900886877');

-- --------------------------------------------------------

--
-- Table structure for table `carrier`
--

CREATE TABLE `carrier` (
  `PartyID` int(11) NOT NULL,
  `Capabilities` varchar(255) DEFAULT NULL,
  `PFM_Score` decimal(3,2) DEFAULT 5.00,
  `Note` text DEFAULT NULL,
  `ServiceArea` varchar(100) DEFAULT NULL,
  `Status` enum('Active','Inactive','Limited') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carrier`
--

INSERT INTO `carrier` (`PartyID`, `Capabilities`, `PFM_Score`, `Note`, `ServiceArea`, `Status`) VALUES
(21, 'Ocean Freight (Container)', 4.80, 'Highly reliable, premium pricing.', 'Global Ocean', 'Active'),
(22, 'Ocean Freight (Container)', 4.70, 'Good EU coverage.', 'Global Ocean', 'Active'),
(23, 'Ocean Freight (Container)', 4.60, 'Strong in Mediterranean.', 'Global Ocean', 'Active'),
(24, 'Ocean Freight (Container)', 4.50, 'Solid performance, good rates.', 'Global Ocean', 'Active'),
(25, 'Ocean Freight (Container)', 4.40, 'Best for US-China lanes.', 'Trans-Pacific Ocean', 'Active'),
(26, 'Ocean Freight (Container)', 4.50, 'Good intra-Asia network.', 'Asia Pacific', 'Active'),
(27, 'Ocean Freight (Container)', 4.30, 'Sometimes delays in port clearing.', 'Global Ocean', 'Active'),
(28, 'Air Freight, Road', 4.90, 'Fast customs clearance in US.', 'Global Air & US Domestic', 'Active'),
(29, 'Air Freight, Road', 4.80, 'Excellent tracking system.', 'Global Air & US Domestic', 'Active'),
(30, 'Air Freight, Road', 4.80, 'Strong in EU logistics.', 'Global Air & EU Domestic', 'Active'),
(31, 'Air Freight (Heavy)', 4.70, 'Best for Middle East transit.', 'Global Air', 'Active'),
(32, 'Air Freight', 4.60, 'Good HK hub connections.', 'Global Air', 'Active'),
(33, 'Air Freight', 4.50, 'Reliable for luxury goods.', 'Global Air', 'Active'),
(34, 'Logistics, Road, Sea', 4.70, 'Complete supply chain solutions.', 'Global', 'Active'),
(35, 'Logistics, Road, Rail', 4.60, 'Strong Euro-Asia rail link.', 'Global', 'Active'),
(36, 'Road, Last-mile', 4.20, 'Nationwide Vietnam coverage.', 'Vietnam Domestic', 'Active'),
(37, 'Road, Last-mile', 3.80, 'Cheaper rates, slower updates.', 'Vietnam Domestic', 'Active'),
(38, 'Road, E-commerce', 4.00, 'Good for B2C parcels.', 'Vietnam Domestic', 'Active'),
(39, 'Road, E-commerce', 4.10, 'Fast urban delivery.', 'Vietnam Domestic', 'Active'),
(40, 'Road, Last-mile', 2.90, 'Frequent delays during rainy season.', 'Vietnam Domestic', 'Limited');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `PartyID` int(11) NOT NULL,
  `CustomerType` enum('B2B','B2C') NOT NULL,
  `TaxCode` varchar(50) DEFAULT NULL,
  `Industry` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`PartyID`, `CustomerType`, `TaxCode`, `Industry`) VALUES
(1, 'B2B', '0101234567', 'Electronics'),
(2, 'B2B', 'US123456789', 'Technology'),
(3, 'B2B', 'JP987654321', 'Electronics'),
(4, 'B2B', 'US456789123', 'Technology'),
(5, 'B2B', 'US789123456', 'Technology'),
(6, 'B2B', 'US321654987', 'Retail'),
(7, 'B2B', 'US654987321', 'Retail'),
(8, 'B2C', NULL, 'Retail'),
(9, 'B2C', NULL, 'Retail'),
(10, 'B2B', 'ES159753486', 'Fashion'),
(11, 'B2B', 'SE753951486', 'Fashion'),
(12, 'B2B', 'US357159486', 'Fashion'),
(13, 'B2B', 'DE147258369', 'Fashion'),
(14, 'B2B', 'UK258369147', 'FMCG'),
(15, 'B2B', 'US369147258', 'FMCG'),
(16, 'B2B', 'CH123987456', 'FMCG'),
(17, 'B2B', 'US789456123', 'FMCG'),
(18, 'B2B', 'US456123789', 'FMCG'),
(19, 'B2B', '0300123456', 'Manufacturing'),
(20, 'B2B', '0300987654', 'FMCG');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `EmployeeID` int(11) NOT NULL,
  `RoleID` int(11) DEFAULT NULL,
  `WarehouseID` int(11) DEFAULT NULL,
  `FullName` varchar(100) DEFAULT NULL,
  `DoB` date DEFAULT NULL,
  `ContactEmail` varchar(100) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`EmployeeID`, `RoleID`, `WarehouseID`, `FullName`, `DoB`, `ContactEmail`, `Phone`) VALUES
(1, 1, 1, 'Nguyen Hai Dang', '1990-05-15', 'dang.nguyen@itl.com', '+84981111222'),
(2, 2, 2, 'Tran Thi Mai Anh', '1988-11-10', 'maianh.tran@itl.com', '+84902222333'),
(3, 3, 3, 'Michael Chen', '1985-08-20', 'michael.chen@itl.com', '+13105550199'),
(4, 4, 4, 'Sarah Weber', '1994-02-28', 'sarah.weber@itl.com', '+49691234567'),
(5, 2, 5, 'David Lim', '1982-04-12', 'david.lim@itl.com', '+6591234567'),
(6, 4, 6, 'Ahmed Al-Fayed', '1991-09-05', 'ahmed.alfayed@itl.com', '+971501234567'),
(7, 4, 7, 'Wang Wei', '1993-12-01', 'wang.wei@itl.com', '+8613800138000'),
(8, 2, 8, 'Jan van der Berg', '1980-03-25', 'jan.vanderberg@itl.com', '+31612345678'),
(9, 4, 9, 'Le Hoang Cuong', '1995-07-19', 'cuong.le@itl.com', '+84934444555'),
(10, 4, 10, 'Pham Thi Dung', '1996-01-30', 'dung.pham@itl.com', '+84945555666'),
(11, 4, 11, 'Yuki Tanaka', '1992-06-14', 'yuki.tanaka@itl.com', '+819012345678'),
(12, 3, 12, 'Emma Watson', '1989-10-22', 'emma.watson@itl.com', '+447700900123'),
(13, 4, 13, 'James Carter', '1987-02-18', 'james.carter@itl.com', '+61412345678'),
(14, 2, 14, 'Kevin Zhang', '1984-05-09', 'kevin.zhang@itl.com', '+85291234567'),
(15, 4, 15, 'Park Ji-sung', '1990-11-30', 'jisung.park@itl.com', '+821012345678'),
(16, 4, 16, 'Raj Patel', '1993-08-21', 'raj.patel@itl.com', '+919876543210'),
(17, 4, 17, 'Ngo Thi Giang', '1997-04-05', 'giang.ngo@itl.com', '+84967777888'),
(18, 4, 18, 'Doan Hai', '1994-12-12', 'hai.doan@itl.com', '+84978888999'),
(19, 2, 19, 'John Smith', '1981-01-15', 'john.smith@itl.com', '+13125550198'),
(20, 4, 20, 'Anna Kowalski', '1995-09-08', 'anna.kowalski@itl.com', '+14165550123');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_log`
--

CREATE TABLE `inventory_log` (
  `LogID` int(11) NOT NULL,
  `ZoneID` int(11) DEFAULT NULL,
  `SKUID` int(11) DEFAULT NULL,
  `AccountID` int(11) DEFAULT NULL,
  `Quantity_Changed` int(11) DEFAULT NULL,
  `Action` enum('IN','OUT','MOVE') DEFAULT NULL,
  `Movement_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_log`
--

INSERT INTO `inventory_log` (`LogID`, `ZoneID`, `SKUID`, `AccountID`, `Quantity_Changed`, `Action`, `Movement_At`) VALUES
(1, 1, 1, 4, 100, 'OUT', '2026-06-01 08:30:00'),
(2, 2, 2, 6, 50, 'MOVE', '2026-06-02 10:00:00'),
(3, 3, 3, 7, 200, 'IN', '2026-06-03 11:15:00'),
(4, 4, 4, 9, 20, 'OUT', '2026-06-04 14:00:00'),
(5, 5, 5, 10, 10, 'IN', '2026-06-05 09:45:00'),
(6, 6, 6, 11, 500, 'OUT', '2026-06-06 16:30:00'),
(7, 7, 7, 13, 300, 'MOVE', '2026-06-07 10:20:00'),
(8, 8, 8, 15, 1000, 'IN', '2026-06-08 13:10:00'),
(9, 9, 9, 16, 2000, 'IN', '2026-06-09 11:00:00'),
(10, 10, 10, 17, 150, 'OUT', '2026-06-10 15:45:00'),
(11, 11, 11, 4, 400, 'MOVE', '2026-06-11 08:00:00'),
(12, 12, 12, 6, 250, 'IN', '2026-06-12 09:30:00'),
(13, 13, 13, 7, 50, 'OUT', '2026-06-13 14:20:00'),
(14, 14, 14, 9, 60, 'IN', '2026-06-14 10:10:00'),
(15, 15, 15, 10, 500, 'MOVE', '2026-06-15 16:00:00'),
(16, 16, 16, 11, 1000, 'OUT', '2026-06-16 11:25:00'),
(17, 17, 17, 13, 30, 'IN', '2026-06-17 08:50:00'),
(18, 18, 18, 15, 15, 'OUT', '2026-06-18 13:40:00'),
(19, 19, 19, 16, 200, 'IN', '2026-06-19 10:05:00'),
(20, 20, 20, 17, 40, 'MOVE', '2026-06-20 15:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `invoice`
--

CREATE TABLE `invoice` (
  `InvoiceID` int(11) NOT NULL,
  `OrderID` int(11) DEFAULT NULL,
  `BilledPartyID` int(11) DEFAULT NULL,
  `InvoiceType` enum('AR_Receivable','AP_Payable') DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL,
  `TotalPreAmount` decimal(15,2) DEFAULT NULL,
  `TaxRate` decimal(5,2) DEFAULT NULL,
  `FinalAmount` decimal(15,2) DEFAULT NULL,
  `IssueDate` date DEFAULT NULL,
  `Note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice`
--

INSERT INTO `invoice` (`InvoiceID`, `OrderID`, `BilledPartyID`, `InvoiceType`, `UserID`, `TotalPreAmount`, `TaxRate`, `FinalAmount`, `IssueDate`, `Note`) VALUES
(1, 1, 1, 'AR_Receivable', 3, 11000.00, 10.00, 12100.00, '2026-05-20', 'Samsung Electronics May Shipment'),
(2, 2, 2, 'AR_Receivable', 12, 5500.00, 0.00, 5500.00, '2026-06-02', 'Apple Air Freight Zero Tax'),
(3, 3, 3, 'AR_Receivable', 3, 7000.00, 10.00, 7700.00, '2026-06-05', 'Sony Corp Ocean Freight'),
(4, NULL, 21, 'AP_Payable', 12, 18000.00, 0.00, 18000.00, '2026-06-01', 'Payment to Maersk for 5 FEU'),
(5, NULL, 28, 'AP_Payable', 3, 4000.00, 0.00, 4000.00, '2026-06-03', 'Payment to FedEx Air capacity'),
(6, 6, 6, 'AR_Receivable', 12, 12000.00, 8.00, 12960.00, '2026-05-30', 'Walmart Global Import'),
(7, 7, 7, 'AR_Receivable', 3, 1500.00, 10.00, 1650.00, '2026-06-08', 'Target Domestic Freight'),
(8, 8, 8, 'AR_Receivable', 12, 45.00, 10.00, 49.50, '2026-06-09', 'Minh Tuan Mobile Last Mile'),
(9, 9, 9, 'AR_Receivable', 3, 60.00, 10.00, 66.00, '2026-06-10', 'Hoang Ha Mobile Last Mile'),
(10, NULL, 36, 'AP_Payable', 12, 5000.00, 10.00, 5500.00, '2026-06-05', 'Payment to Viettel Post Monthly'),
(11, 11, 11, 'AR_Receivable', 3, 3500.00, 0.00, 3500.00, '2026-06-11', 'H&M Ocean Freight'),
(12, 12, 12, 'AR_Receivable', 12, 25000.00, 5.00, 26250.00, '2026-06-12', 'Nike Bulk Shipment Air'),
(13, 13, 13, 'AR_Receivable', 3, 1800.00, 10.00, 1980.00, '2026-06-13', 'Adidas Intra-Europe'),
(14, NULL, 34, 'AP_Payable', 12, 15000.00, 0.00, 15000.00, '2026-06-10', 'Payment to Kuehne+Nagel Logistics'),
(15, 15, 15, 'AR_Receivable', 3, 8500.00, 8.00, 9180.00, '2026-06-15', 'P&G Trans-Pacific'),
(16, 16, 16, 'AR_Receivable', 12, 900.00, 10.00, 990.00, '2026-06-16', 'Nestle Cross-border Trucking'),
(17, 17, 17, 'AR_Receivable', 3, 5000.00, 0.00, 5000.00, '2026-06-17', 'PepsiCo Export'),
(18, 18, 18, 'AR_Receivable', 12, 6000.00, 10.00, 6600.00, '2026-06-18', 'Coca Cola Import'),
(19, NULL, 39, 'AP_Payable', 3, 2000.00, 10.00, 2200.00, '2026-06-15', 'Payment to J&T Express VN'),
(20, 20, 20, 'AR_Receivable', 12, 1200.00, 8.00, 1296.00, '2026-06-20', 'Vinamilk Domestic Distribution');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_line`
--

CREATE TABLE `invoice_line` (
  `LineID` int(11) NOT NULL,
  `InvoiceID` int(11) DEFAULT NULL,
  `BillingID` int(11) DEFAULT NULL,
  `ChargeMethod` varchar(50) DEFAULT NULL,
  `Quantity` decimal(10,2) DEFAULT NULL,
  `UnitPrice` decimal(15,2) DEFAULT NULL,
  `LineTotal` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_line`
--

INSERT INTO `invoice_line` (`LineID`, `InvoiceID`, `BillingID`, `ChargeMethod`, `Quantity`, `UnitPrice`, `LineTotal`) VALUES
(1, 1, 1, 'Per Unit', 3.00, 3500.00, 10500.00),
(2, 1, 8, 'Flat', 1.00, 500.00, 500.00),
(3, 2, 3, 'Weight', 1000.00, 5.50, 5500.00),
(4, 3, 1, 'Per Unit', 2.00, 3500.00, 7000.00),
(5, 4, 1, 'Per Unit', 5.00, 3500.00, 17500.00),
(6, 4, 11, 'Flat', 1.00, 500.00, 500.00),
(7, 5, 3, 'Weight', 700.00, 5.50, 3850.00),
(8, 5, 7, 'Flat', 1.00, 150.00, 150.00),
(9, 6, 1, 'Per Unit', 3.00, 3500.00, 10500.00),
(10, 6, 9, 'Per Unit', 3.00, 500.00, 1500.00),
(11, 7, 4, 'Flat', 1.00, 1200.00, 1200.00),
(12, 7, 10, 'Flat', 1.00, 300.00, 300.00),
(13, 8, 6, 'Flat', 3.00, 15.00, 45.00),
(14, 9, 6, 'Flat', 4.00, 15.00, 60.00),
(15, 10, 4, 'Flat', 4.00, 1200.00, 4800.00),
(16, 10, 7, 'Flat', 1.00, 200.00, 200.00),
(17, 11, 2, 'Per Unit', 1.00, 1800.00, 1800.00),
(18, 11, 17, 'Flat', 1.00, 1700.00, 1700.00),
(19, 12, 3, 'Weight', 4500.00, 5.50, 24750.00),
(20, 12, 7, 'Flat', 1.00, 250.00, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `ItemID` int(11) NOT NULL,
  `SKUID` int(11) DEFAULT NULL,
  `ZoneID` int(11) DEFAULT NULL,
  `OrderID` int(11) DEFAULT NULL,
  `Item_Name` varchar(100) DEFAULT NULL,
  `StockStatus` enum('In Stock','Reserved','Shipped','Damaged') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`ItemID`, `SKUID`, `ZoneID`, `OrderID`, `Item_Name`, `StockStatus`) VALUES
(1, 1, 1, 1, 'Samsung Galaxy S24 Ultra Black', 'Shipped'),
(2, 2, 2, 2, 'iPhone 15 Pro Max Titanium', 'Reserved'),
(3, 3, 3, 3, 'PS5 Digital Edition', 'In Stock'),
(4, 4, 4, 4, 'Dell XPS 15 Core i9', 'Reserved'),
(5, 5, 5, 5, 'HP ProLiant Gen10', 'In Stock'),
(6, 6, 6, 6, 'Nike AF1 White Size 10', 'Shipped'),
(7, 7, 7, 7, 'Adidas Ultraboost Black', 'Reserved'),
(8, 8, 8, 8, 'Zara Summer Dress M', 'In Stock'),
(9, 9, 9, 9, 'H&M Basic T-Shirt White', 'In Stock'),
(10, 10, 10, 10, 'Dove Shampoo 600ml', 'Shipped'),
(11, 11, 11, 11, 'Ariel Detergent 3kg', 'Reserved'),
(12, 12, 12, 12, 'Nescafe Gold 500g', 'In Stock'),
(13, 13, 13, 13, 'Pepsi Cola 330ml Can', 'Shipped'),
(14, 14, 14, 14, 'Coca-Cola 330ml Can', 'In Stock'),
(15, 15, 15, 15, 'Hoa Phat Steel Rebar D10', 'Damaged'),
(16, 16, 16, 16, 'Vinamilk Fresh Milk 1L Box', 'Reserved'),
(17, 17, 17, 17, 'MacBook Pro 16\" M3 Max', 'Shipped'),
(18, 18, 18, 18, 'Sony Bravia OLED 65A95L', 'In Stock'),
(19, 19, 19, 19, 'AirPods Pro Gen 2', 'Reserved'),
(20, 20, 20, 20, 'Dell Ultrasharp U2723QE', 'In Stock');

-- --------------------------------------------------------

--
-- Table structure for table `operational_exception`
--

CREATE TABLE `operational_exception` (
  `ExceptionID` int(11) NOT NULL,
  `CarrierID` int(11) DEFAULT NULL,
  `ShipmentID` int(11) DEFAULT NULL,
  `AccountID` int(11) DEFAULT NULL,
  `IssueType` varchar(100) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `SeverityLevel` enum('Low','Medium','High','Critical') DEFAULT NULL,
  `ApprovalStatus` varchar(50) DEFAULT NULL,
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `operational_exception`
--

INSERT INTO `operational_exception` (`ExceptionID`, `CarrierID`, `ShipmentID`, `AccountID`, `IssueType`, `Description`, `SeverityLevel`, `ApprovalStatus`, `CreatedAt`) VALUES
(1, 21, 1, 4, 'Weather Delay', 'Vessel delayed by typhoon in Pacific', 'High', 'Approved', '2026-06-02 19:17:04'),
(2, 28, 2, 6, 'Customs Hold', 'Random inspection at FRA airport', 'Medium', 'Pending', '2026-06-02 19:17:04'),
(3, 36, 3, 7, 'Vehicle Breakdown', 'Truck engine failure in Nghe An', 'High', 'Approved', '2026-06-02 19:17:04'),
(4, 22, 4, 9, 'Port Congestion', 'Waiting for berth at Suez Canal', 'Medium', 'Approved', '2026-06-02 19:17:04'),
(5, 34, 6, 11, 'Weather Delay', 'Train stuck due to heavy snow in Denver', 'Medium', 'Approved', '2026-06-02 19:17:04'),
(6, 31, 8, 15, 'Missed Connection', 'Cargo missed connecting flight in Dubai', 'High', 'Approved', '2026-06-02 19:17:04'),
(7, 35, 9, 16, 'Documentation Error', 'Wrong HS Code at border crossing', 'Critical', 'Pending', '2026-06-02 19:17:04'),
(8, 36, 12, 6, 'Traffic Jam', 'Accident blocking main road in HCMC', 'Low', 'Approved', '2026-06-02 19:17:04'),
(9, 23, 14, 9, 'Equipment Shortage', 'No empty chassis available at destination', 'Medium', 'Pending', '2026-06-02 19:17:04'),
(10, 24, 15, 10, 'Weather Delay', 'Vessel rerouted due to cyclone in Arabian Sea', 'High', 'Approved', '2026-06-02 19:17:04'),
(11, 28, 16, 11, 'Driver Hours Exceeded', 'Driver mandated rest period required', 'Low', 'Approved', '2026-06-02 19:17:04'),
(12, 25, 18, 15, 'Port Strike', 'Workers strike at Rotterdam port', 'Critical', 'Pending', '2026-06-02 19:17:04'),
(13, 32, 20, 17, 'Flight Cancellation', 'Flight cancelled due to typhoon warning', 'High', 'Approved', '2026-06-02 19:17:04'),
(14, 37, 13, 7, 'Customer Not Available', 'Receiver office closed upon arrival', 'Low', 'Approved', '2026-06-02 19:17:04'),
(15, 40, 13, 7, 'Damaged Goods', 'Box crushed during loading', 'Critical', 'Under Investigation', '2026-06-02 19:17:04'),
(16, 39, 12, 6, 'Lost in Transit', 'Parcel cannot be located at hub', 'Critical', 'Under Investigation', '2026-06-02 19:17:04'),
(17, 21, 1, 4, 'Piracy Threat', 'Vessel rerouted for security reasons', 'High', 'Approved', '2026-06-02 19:17:04'),
(18, 29, 11, 4, 'Overweight Cargo', 'Actual weight 500kg over declared', 'Medium', 'Pending', '2026-06-02 19:17:04'),
(19, 38, 12, 6, 'Wrong Address', 'Customer provided incorrect zip code', 'Low', 'Approved', '2026-06-02 19:17:04'),
(20, 30, 10, 17, 'Temperature Deviation', 'Reefer container temp dropped by 5C', 'Critical', 'Under Investigation', '2026-06-02 19:17:04');

-- --------------------------------------------------------

--
-- Table structure for table `order_detailed`
--

CREATE TABLE `order_detailed` (
  `OrderID` int(11) NOT NULL,
  `SKUID` int(11) NOT NULL,
  `Quantity` int(11) DEFAULT NULL,
  `Weight` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_detailed`
--

INSERT INTO `order_detailed` (`OrderID`, `SKUID`, `Quantity`, `Weight`) VALUES
(1, 1, 500, 125.00),
(1, 2, 800, 200.00),
(2, 17, 300, 600.00),
(3, 3, 150, 450.00),
(4, 4, 100, 250.00),
(4, 20, 50, 400.00),
(5, 5, 20, 500.00),
(6, 6, 2000, 2000.00),
(7, 7, 1500, 1500.00),
(8, 2, 5, 1.25),
(9, 1, 10, 2.50),
(10, 8, 5000, 1500.00),
(11, 9, 10000, 2500.00),
(12, 6, 3000, 3000.00),
(13, 7, 2500, 2500.00),
(14, 10, 1000, 650.00),
(15, 11, 1500, 4500.00),
(16, 12, 800, 400.00),
(17, 13, 400, 800.00),
(18, 14, 450, 900.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_info`
--

CREATE TABLE `order_info` (
  `OrderID` int(11) NOT NULL,
  `AccountID` int(11) DEFAULT NULL,
  `CustomerID` int(11) DEFAULT NULL,
  `PickupAddress` text DEFAULT NULL,
  `OrderDate` datetime DEFAULT NULL,
  `ExpectedDeliveryDate` datetime DEFAULT NULL,
  `ShippingStatus` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_info`
--

INSERT INTO `order_info` (`OrderID`, `AccountID`, `CustomerID`, `PickupAddress`, `OrderDate`, `ExpectedDeliveryDate`, `ShippingStatus`) VALUES
(1, 4, 1, 'Yen Phong IP, Bac Ninh, Vietnam', '2026-06-01 08:00:00', '2026-06-15 12:00:00', 'Shipped'),
(2, 6, 2, '1 Apple Park Way, Cupertino, CA', '2026-06-02 09:30:00', '2026-06-20 17:00:00', 'In Transit'),
(3, 7, 3, 'Konan, Minato-ku, Tokyo', '2026-06-03 10:15:00', '2026-06-10 10:00:00', 'Processing'),
(4, 9, 4, 'Round Rock, TX, USA', '2026-06-04 11:00:00', '2026-06-25 15:00:00', 'Pending'),
(5, 10, 5, 'Spring, TX, USA', '2026-06-05 14:20:00', '2026-06-28 14:00:00', 'Pending'),
(6, 11, 6, 'Bentonville, AR, USA', '2026-06-06 08:45:00', '2026-06-30 18:00:00', 'Processing'),
(7, 13, 7, 'Minneapolis, MN, USA', '2026-06-07 09:00:00', '2026-07-02 12:00:00', 'Shipped'),
(8, 15, 8, '43 Tran Quang Khai, Dist 1, HCMC', '2026-06-08 15:30:00', '2026-06-09 18:00:00', 'Delivered'),
(9, 16, 9, '122 Thai Ha, Hanoi, Vietnam', '2026-06-09 10:00:00', '2026-06-10 10:00:00', 'Delivered'),
(10, 17, 10, 'Arteixo, Spain', '2026-06-10 11:45:00', '2026-06-22 15:00:00', 'In Transit'),
(11, 4, 11, 'Stockholm, Sweden', '2026-06-11 13:10:00', '2026-06-24 16:00:00', 'Processing'),
(12, 6, 12, 'Beaverton, OR, USA', '2026-06-12 14:00:00', '2026-06-26 12:00:00', 'Pending'),
(13, 7, 13, 'Herzogenaurach, Germany', '2026-06-13 09:20:00', '2026-06-25 09:00:00', 'Processing'),
(14, 9, 14, 'London, UK', '2026-06-14 16:30:00', '2026-06-28 17:00:00', 'Shipped'),
(15, 10, 15, 'Cincinnati, OH, USA', '2026-06-15 10:05:00', '2026-07-05 14:00:00', 'In Transit'),
(16, 11, 16, 'Vevey, Switzerland', '2026-06-16 11:15:00', '2026-06-29 11:00:00', 'Pending'),
(17, 13, 17, 'Purchase, NY, USA', '2026-06-17 14:40:00', '2026-07-08 15:00:00', 'Processing'),
(18, 15, 18, 'Atlanta, GA, USA', '2026-06-18 08:50:00', '2026-07-10 10:00:00', 'Shipped'),
(19, 16, 19, 'Hai Ba Trung, Hanoi, Vietnam', '2026-06-19 15:20:00', '2026-06-25 15:00:00', 'In Transit'),
(20, 17, 20, 'Dist 7, HCMC, Vietnam', '2026-06-20 09:00:00', '2026-06-22 09:00:00', 'Processing');

-- --------------------------------------------------------

--
-- Table structure for table `payment_transaction`
--

CREATE TABLE `payment_transaction` (
  `PaymentID` int(11) NOT NULL,
  `InvoiceID` int(11) DEFAULT NULL,
  `AmountPaid` decimal(15,2) DEFAULT NULL,
  `ReferenceCode` varchar(50) DEFAULT NULL,
  `PaymentDate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_transaction`
--

INSERT INTO `payment_transaction` (`PaymentID`, `InvoiceID`, `AmountPaid`, `ReferenceCode`, `PaymentDate`) VALUES
(1, 1, 12100.00, 'WIRE-SAM-991', '2026-05-25 10:00:00'),
(2, 2, 5500.00, 'WIRE-APP-882', '2026-06-05 14:30:00'),
(3, 3, 7700.00, 'SWIFT-SON-773', '2026-06-10 09:15:00'),
(4, 4, 18000.00, 'OUT-MAE-664', '2026-06-02 11:00:00'),
(5, 5, 4000.00, 'OUT-FED-555', '2026-06-05 16:45:00'),
(6, 6, 12960.00, 'WIRE-WAL-446', '2026-06-10 13:20:00'),
(7, 7, 1650.00, 'ACH-TAR-337', '2026-06-15 08:30:00'),
(8, 8, 49.50, 'MOMO-MIN-228', '2026-06-09 18:05:00'),
(9, 9, 66.00, 'VNPAY-HOA-119', '2026-06-10 10:10:00'),
(10, 10, 5500.00, 'OUT-VTP-001', '2026-06-08 14:00:00'),
(11, 11, 3500.00, 'WIRE-HM-002', '2026-06-18 11:11:00'),
(12, 12, 13000.00, 'WIRE-NIK-003A', '2026-06-15 09:00:00'),
(13, 12, 13250.00, 'WIRE-NIK-003B', '2026-06-25 09:00:00'),
(14, 13, 1980.00, 'SEPA-ADI-004', '2026-06-20 15:30:00'),
(15, 14, 15000.00, 'OUT-KUE-005', '2026-06-12 10:00:00'),
(16, 15, 9180.00, 'WIRE-PG-006', '2026-06-22 14:45:00'),
(17, 16, 990.00, 'SEPA-NES-007', '2026-06-20 08:20:00'),
(18, 17, 5000.00, 'WIRE-PEP-008', '2026-06-25 11:30:00'),
(19, 18, 6600.00, 'WIRE-COC-009', '2026-06-26 16:15:00'),
(20, 19, 2200.00, 'OUT-JNT-010', '2026-06-18 10:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `RoleID` int(11) NOT NULL,
  `RoleName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`RoleID`, `RoleName`) VALUES
(1, 'Admin'),
(2, 'Manager'),
(3, 'Accountant'),
(4, 'Operation Staff');

-- --------------------------------------------------------

--
-- Table structure for table `route`
--

CREATE TABLE `route` (
  `RouteID` int(11) NOT NULL,
  `RouteName` varchar(100) DEFAULT NULL,
  `StartLocation` varchar(100) DEFAULT NULL,
  `EndLocation` varchar(100) DEFAULT NULL,
  `TransportMode` enum('Air','Ocean','Road','Rail') DEFAULT NULL,
  `EstimatedDistance` decimal(10,2) DEFAULT NULL,
  `EstimatedDuration` decimal(10,2) DEFAULT NULL,
  `DurationUnit` enum('Hours','Days','Months') DEFAULT 'Days'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `route`
--

INSERT INTO `route` (`RouteID`, `RouteName`, `StartLocation`, `EndLocation`, `TransportMode`, `EstimatedDistance`, `EstimatedDuration`, `DurationUnit`) VALUES
(1, 'Trans-Pacific Ocean', 'Shenzhen Port (CN)', 'Long Beach Port (US)', 'Ocean', 11000.00, 18.00, 'Days'),
(2, 'Euro-Asia Express Air', 'Noi Bai Airport (VN)', 'Frankfurt Airport (DE)', 'Air', 8500.00, 12.00, 'Hours'),
(3, 'North-South VN Trucking', 'Hanoi (VN)', 'Ho Chi Minh City (VN)', 'Road', 1700.00, 3.00, 'Days'),
(4, 'Asia-EU Ocean', 'Shanghai Port (CN)', 'Rotterdam Port (NL)', 'Ocean', 19000.00, 35.00, 'Days'),
(5, 'Trans-Atlantic Ocean', 'New York Port (US)', 'Southampton Port (UK)', 'Ocean', 5500.00, 10.00, 'Days'),
(6, 'US Domestic Rail', 'Chicago (US)', 'Los Angeles (US)', 'Rail', 3200.00, 4.00, 'Days'),
(7, 'Intra-Asia Ocean', 'Singapore Port', 'Busan Port (KR)', 'Ocean', 4500.00, 7.00, 'Days'),
(8, 'Middle East Air Link', 'Dubai (UAE)', 'London Heathrow (UK)', 'Air', 5500.00, 7.00, 'Hours'),
(9, 'China-Europe Rail', 'Chengdu (CN)', 'Lodz (PL)', 'Rail', 9800.00, 14.00, 'Days'),
(10, 'EU Domestic Trucking', 'Frankfurt (DE)', 'Paris (FR)', 'Road', 570.00, 8.00, 'Hours'),
(11, 'Japan-US Air', 'Narita Airport (JP)', 'LAX Airport (US)', 'Air', 8800.00, 10.00, 'Hours'),
(12, 'Vietnam Last Mile SGN', 'Di An (Binh Duong)', 'District 1 (HCMC)', 'Road', 25.00, 2.00, 'Hours'),
(13, 'Vietnam Last Mile HAN', 'Yen Phong (Bac Ninh)', 'Hoan Kiem (Hanoi)', 'Road', 35.00, 1.50, 'Hours'),
(14, 'Australia-Asia Ocean', 'Sydney Port (AU)', 'Singapore Port', 'Ocean', 6300.00, 12.00, 'Days'),
(15, 'India-Europe Ocean', 'Nhava Sheva Port (IN)', 'Hamburg Port (DE)', 'Ocean', 11500.00, 22.00, 'Days'),
(16, 'US East-West Trucking', 'New York (US)', 'San Francisco (US)', 'Road', 4600.00, 5.00, 'Days'),
(17, 'LATAM-US Air', 'Sao Paulo (BR)', 'Miami (US)', 'Air', 6500.00, 8.00, 'Hours'),
(18, 'Africa-Europe Ocean', 'Durban Port (ZA)', 'Rotterdam Port (NL)', 'Ocean', 12000.00, 24.00, 'Days'),
(19, 'Canada-US Trucking', 'Toronto (CA)', 'Chicago (US)', 'Road', 850.00, 12.00, 'Hours'),
(20, 'Korea-Vietnam Air', 'Incheon Airport (KR)', 'Tan Son Nhat (VN)', 'Air', 3600.00, 5.00, 'Hours');

-- --------------------------------------------------------

--
-- Table structure for table `shipment`
--

CREATE TABLE `shipment` (
  `ShipmentID` int(11) NOT NULL,
  `RouteID` int(11) DEFAULT NULL,
  `AssetID` int(11) DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `PlannedDeparture` datetime DEFAULT NULL,
  `DeliveryDeadline` datetime DEFAULT NULL,
  `EstimatedArrival` datetime DEFAULT NULL,
  `ActualDeparture` datetime DEFAULT NULL,
  `ActualArrival` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipment`
--

INSERT INTO `shipment` (`ShipmentID`, `RouteID`, `AssetID`, `Status`, `PlannedDeparture`, `DeliveryDeadline`, `EstimatedArrival`, `ActualDeparture`, `ActualArrival`) VALUES
(1, 1, 1, 'Delivered', '2026-05-01 10:00:00', '2026-05-20 10:00:00', '2026-05-19 10:00:00', '2026-05-01 12:00:00', '2026-05-18 15:00:00'),
(2, 2, 6, 'Delivered', '2026-06-01 08:00:00', '2026-06-02 08:00:00', '2026-06-01 20:00:00', '2026-06-01 08:15:00', '2026-06-01 20:10:00'),
(3, 3, 13, 'In Transit', '2026-06-05 06:00:00', '2026-06-09 06:00:00', '2026-06-08 06:00:00', '2026-06-05 07:00:00', NULL),
(4, 4, 2, 'In Transit', '2026-05-20 14:00:00', '2026-06-25 14:00:00', '2026-06-24 14:00:00', '2026-05-20 16:30:00', NULL),
(5, 5, 3, 'Pending', '2026-06-15 09:00:00', '2026-06-26 09:00:00', '2026-06-25 09:00:00', NULL, NULL),
(6, 6, 11, 'Delivered', '2026-05-25 11:00:00', '2026-05-30 11:00:00', '2026-05-29 11:00:00', '2026-05-25 11:05:00', '2026-05-29 14:00:00'),
(7, 7, 4, 'In Transit', '2026-06-01 16:00:00', '2026-06-09 16:00:00', '2026-06-08 16:00:00', '2026-06-01 18:00:00', NULL),
(8, 8, 9, 'Delivered', '2026-06-02 10:00:00', '2026-06-03 10:00:00', '2026-06-02 17:00:00', '2026-06-02 10:15:00', '2026-06-02 16:50:00'),
(9, 9, 12, 'In Transit', '2026-06-01 05:00:00', '2026-06-16 05:00:00', '2026-06-15 05:00:00', '2026-06-01 05:30:00', NULL),
(10, 10, 18, 'Pending', '2026-06-10 08:00:00', '2026-06-11 08:00:00', '2026-06-10 16:00:00', NULL, NULL),
(11, 11, 10, 'Delivered', '2026-05-28 22:00:00', '2026-05-30 22:00:00', '2026-05-29 08:00:00', '2026-05-28 22:20:00', '2026-05-29 08:15:00'),
(12, 12, 15, 'Delivered', '2026-06-05 14:00:00', '2026-06-05 18:00:00', '2026-06-05 16:00:00', '2026-06-05 14:10:00', '2026-06-05 16:05:00'),
(13, 13, 16, 'In Transit', '2026-06-06 09:00:00', '2026-06-06 12:00:00', '2026-06-06 10:30:00', '2026-06-06 09:05:00', NULL),
(14, 14, 5, 'Pending', '2026-06-20 12:00:00', '2026-07-03 12:00:00', '2026-07-02 12:00:00', NULL, NULL),
(15, 15, 1, 'In Transit', '2026-05-22 08:00:00', '2026-06-15 08:00:00', '2026-06-13 08:00:00', '2026-05-22 09:30:00', NULL),
(16, 16, 19, 'Delivered', '2026-05-25 06:00:00', '2026-05-31 06:00:00', '2026-05-30 06:00:00', '2026-05-25 06:15:00', '2026-05-30 10:00:00'),
(17, 17, 7, 'Pending', '2026-06-12 23:00:00', '2026-06-14 23:00:00', '2026-06-13 07:00:00', NULL, NULL),
(18, 18, 2, 'In Transit', '2026-05-18 10:00:00', '2026-06-12 10:00:00', '2026-06-11 10:00:00', '2026-05-18 11:45:00', NULL),
(19, 19, 18, 'Delivered', '2026-06-01 07:00:00', '2026-06-02 07:00:00', '2026-06-01 19:00:00', '2026-06-01 07:10:00', '2026-06-01 18:50:00'),
(20, 20, 8, 'In Transit', '2026-06-06 13:00:00', '2026-06-07 13:00:00', '2026-06-06 18:00:00', '2026-06-06 13:15:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shipment_order`
--

CREATE TABLE `shipment_order` (
  `ShipmentID` int(11) NOT NULL,
  `OrderID` int(11) NOT NULL,
  `LegSequence` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipment_order`
--

INSERT INTO `shipment_order` (`ShipmentID`, `OrderID`, `LegSequence`) VALUES
(1, 1, 1),
(1, 2, 2),
(2, 3, 1),
(3, 4, 1),
(4, 5, 1),
(6, 6, 1),
(7, 7, 1),
(8, 8, 1),
(8, 9, 2),
(9, 10, 1),
(10, 11, 1),
(11, 12, 1),
(11, 13, 2),
(12, 14, 1),
(13, 15, 1),
(14, 16, 1),
(15, 17, 1),
(16, 18, 1),
(18, 19, 1),
(19, 20, 1);

-- --------------------------------------------------------

--
-- Table structure for table `sku`
--

CREATE TABLE `sku` (
  `SKUID` int(11) NOT NULL,
  `SKUName` varchar(100) DEFAULT NULL,
  `UOM` varchar(20) DEFAULT NULL,
  `Quantity` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sku`
--

INSERT INTO `sku` (`SKUID`, `SKUName`, `UOM`, `Quantity`) VALUES
(1, 'Samsung Galaxy S24 Ultra', 'Piece', 5000),
(2, 'iPhone 15 Pro Max', 'Piece', 8000),
(3, 'Sony PlayStation 5', 'Piece', 2000),
(4, 'Dell XPS 15 Laptop', 'Piece', 1500),
(5, 'HP ProLiant Server', 'Unit', 300),
(6, 'Nike Air Force 1', 'Pair', 10000),
(7, 'Adidas Ultraboost', 'Pair', 8500),
(8, 'Zara Summer Dress', 'Piece', 12000),
(9, 'H&M Basic T-Shirt', 'Piece', 25000),
(10, 'Unilever Dove Shampoo', 'Carton', 4000),
(11, 'P&G Ariel Detergent', 'Carton', 5500),
(12, 'Nestle Nescafe 500g', 'Carton', 3000),
(13, 'Pepsi Cola 330ml Can', 'Pallet', 800),
(14, 'Coca-Cola 330ml Can', 'Pallet', 900),
(15, 'Hoa Phat Steel Rebar', 'Ton', 10000),
(16, 'Vinamilk Fresh Milk 1L', 'Carton', 6000),
(17, 'Apple MacBook Pro 16\"', 'Piece', 1200),
(18, 'Sony Bravia OLED TV 65\"', 'Piece', 400),
(19, 'AirPods Pro Gen 2', 'Piece', 6000),
(20, 'Dell Ultrasharp Monitor', 'Piece', 800);

-- --------------------------------------------------------

--
-- Table structure for table `system_audit_log`
--

CREATE TABLE `system_audit_log` (
  `LogID` int(11) NOT NULL,
  `AccountID` int(11) DEFAULT NULL,
  `TableName` varchar(50) DEFAULT NULL,
  `ActionType` varchar(50) DEFAULT NULL,
  `RecordID` int(11) DEFAULT NULL,
  `ActionTime` datetime DEFAULT current_timestamp(),
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_audit_log`
--

INSERT INTO `system_audit_log` (`LogID`, `AccountID`, `TableName`, `ActionType`, `RecordID`, `ActionTime`, `Description`) VALUES
(1, 1, 'Account', 'CREATE', 20, '2026-06-02 19:17:04', 'Created new user ops_anna'),
(2, 1, 'Role', 'UPDATE', 3, '2026-06-02 19:17:04', 'Modified Accountant permissions'),
(3, 2, 'Billing_Structure', 'CREATE', 21, '2026-06-02 19:17:04', 'Added PSS surcharge'),
(4, 2, 'Order_Info', 'UPDATE', 5, '2026-06-02 19:17:04', 'Approved heavy load order'),
(5, 1, 'Account', 'UPDATE', 18, '2026-06-02 19:17:04', 'Locked account ops_hai due to failed logins'),
(6, 3, 'Invoice', 'CREATE', 20, '2026-06-02 19:17:04', 'Generated invoice for Vinamilk'),
(7, 4, 'Operational_Exception', 'UPDATE', 1, '2026-06-02 19:17:04', 'Approved weather delay request'),
(8, 3, 'Invoice', 'DELETE', 5, '2026-06-02 19:17:04', 'Cancelled duplicate AP invoice'),
(9, 1, 'Customer', 'CREATE', 21, '2026-06-02 19:17:04', 'Added new B2B client Intel'),
(10, 1, 'Carrier', 'UPDATE', 40, '2026-06-02 19:17:04', 'Updated Status to Limited'),
(11, 2, 'Route', 'CREATE', 21, '2026-06-02 19:17:04', 'Added new air route SGN-ICN'),
(12, 2, 'Transport_Asset', 'UPDATE', 5, '2026-06-02 19:17:04', 'Maintenance status update'),
(13, 1, 'System_Notification', 'CREATE', 1, '2026-06-02 19:17:04', 'Broadcasted system update'),
(14, 1, 'Employee', 'UPDATE', 10, '2026-06-02 19:17:04', 'Updated phone number'),
(15, 3, 'Payment_Transaction', 'CREATE', 20, '2026-06-02 19:17:04', 'Recorded payment from J&T'),
(16, 4, 'Shipment', 'UPDATE', 2, '2026-06-02 19:17:04', 'Marked Shipment 2 as Delivered'),
(17, 1, 'SKU', 'CREATE', 21, '2026-06-02 19:17:04', 'Added new product line'),
(18, 4, 'Items', 'UPDATE', 1, '2026-06-02 19:17:04', 'Changed StockStatus to Shipped'),
(19, 2, 'Warehouse_Zone', 'UPDATE', 1, '2026-06-02 19:17:04', 'Increased threshold to 6000'),
(20, 4, 'Inventory_Log', 'CREATE', 20, '2026-06-02 19:17:04', 'Logged MOVE action');

-- --------------------------------------------------------

--
-- Table structure for table `system_notification`
--

CREATE TABLE `system_notification` (
  `NotifID` int(11) NOT NULL,
  `AccountID` int(11) DEFAULT NULL,
  `Title` varchar(100) DEFAULT NULL,
  `Message` text DEFAULT NULL,
  `IsRead` enum('0','1') DEFAULT '0',
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_notification`
--

INSERT INTO `system_notification` (`NotifID`, `AccountID`, `Title`, `Message`, `IsRead`, `CreatedAt`) VALUES
(1, 1, 'System Maintenance', 'Server reboot scheduled at 2 AM UTC', '0', '2026-06-02 19:17:04'),
(2, 2, 'Carrier Alert', 'Carrier 40 PFM Score dropped to 2.90', '0', '2026-06-02 19:17:04'),
(3, 3, 'Overdue Invoice', 'Invoice #15 is past due 5 days', '0', '2026-06-02 19:17:04'),
(4, 4, 'Exception Alert', 'Critical: Documentation error on Shipment 9', '0', '2026-06-02 19:17:04'),
(5, 5, 'Route Changed', 'Route 1 updated due to typhoon', '0', '2026-06-02 19:17:04'),
(6, 6, 'Low Stock', 'SKU 5 running low in Zone 5', '0', '2026-06-02 19:17:04'),
(7, 1, 'Security Alert', '3 failed login attempts for ops_hai', '0', '2026-06-02 19:17:04'),
(8, 2, 'Approval Needed', '5 exceptions pending your review', '0', '2026-06-02 19:17:04'),
(9, 3, 'Payment Received', 'Wire transfer 13250 USD from Nike cleared', '0', '2026-06-02 19:17:04'),
(10, 4, 'New Order Assigned', 'Order 10 assigned to your EU region', '0', '2026-06-02 19:17:04'),
(11, 5, 'Report Ready', 'Monthly Carrier Performance Report generated', '0', '2026-06-02 19:17:04'),
(12, 6, 'Inventory Count', 'Cycle count required for Zone 12', '0', '2026-06-02 19:17:04'),
(13, 1, 'Backup Complete', 'Daily DB backup successful', '0', '2026-06-02 19:17:04'),
(14, 2, 'Budget Warning', 'Q2 logistics budget reached 85%', '0', '2026-06-02 19:17:04'),
(15, 3, 'Tax Rate Update', 'EU VAT rules updated for Q3', '0', '2026-06-02 19:17:04'),
(16, 4, 'Customer Complaint', 'Ticket #1024 opened for Order 9', '0', '2026-06-02 19:17:04'),
(17, 5, 'Schedule Update', 'Warehouse 3 shift changed for holiday', '0', '2026-06-02 19:17:04'),
(18, 6, 'Equipment Maintenance', 'Forklift 2 out of service', '0', '2026-06-02 19:17:04'),
(19, 1, 'New User', 'User ops_anna created successfully', '0', '2026-06-02 19:17:04'),
(20, 2, 'KPI Milestone', 'On-time delivery reached 95% this week', '0', '2026-06-02 19:17:04');

-- --------------------------------------------------------

--
-- Table structure for table `tracking_log`
--

CREATE TABLE `tracking_log` (
  `LogID` int(11) NOT NULL,
  `ShipmentID` int(11) DEFAULT NULL,
  `AccountID` int(11) DEFAULT NULL,
  `Timestamp` datetime DEFAULT NULL,
  `CheckpointLocation` varchar(255) DEFAULT NULL,
  `WeatherCondition` enum('Clear','Rain','Heavy Rain','Snowstorm','Fog','Typhoon','Sandstorm') DEFAULT NULL,
  `TrafficDelayTime` int(11) DEFAULT NULL,
  `TrafficDelayTimeUnit` enum('Minutes','Hours','Days') DEFAULT 'Minutes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tracking_log`
--

INSERT INTO `tracking_log` (`LogID`, `ShipmentID`, `AccountID`, `Timestamp`, `CheckpointLocation`, `WeatherCondition`, `TrafficDelayTime`, `TrafficDelayTimeUnit`) VALUES
(1, 1, 4, '2026-05-05 10:00:00', 'Port of Singapore', 'Clear', 0, 'Minutes'),
(2, 1, 4, '2026-05-10 14:00:00', 'Pacific Ocean', 'Typhoon', 2, 'Days'),
(3, 2, 6, '2026-06-01 12:00:00', 'Noi Bai Cargo Terminal', 'Rain', 30, 'Minutes'),
(4, 2, 6, '2026-06-01 18:00:00', 'Dubai Int Airport Transit', 'Clear', 0, 'Minutes'),
(5, 3, 7, '2026-06-06 08:00:00', 'Thanh Hoa Province', 'Heavy Rain', 4, 'Hours'),
(6, 4, 9, '2026-05-25 16:00:00', 'Port of Colombo', 'Clear', 0, 'Minutes'),
(7, 4, 9, '2026-06-10 09:00:00', 'Suez Canal', 'Sandstorm', 1, 'Days'),
(8, 6, 11, '2026-05-27 14:00:00', 'Denver Rail Yard', 'Snowstorm', 12, 'Hours'),
(9, 7, 13, '2026-06-03 11:00:00', 'South China Sea', 'Clear', 0, 'Minutes'),
(10, 8, 15, '2026-06-02 12:30:00', 'Istanbul Airspace', 'Clear', 0, 'Minutes'),
(11, 9, 16, '2026-06-05 10:00:00', 'Kazakhstan Border', 'Clear', 2, 'Days'),
(12, 10, 17, '2026-06-10 12:00:00', 'Aachen, Germany', 'Clear', 45, 'Minutes'),
(13, 11, 4, '2026-05-29 02:00:00', 'Over Pacific Ocean', 'Clear', 0, 'Minutes'),
(14, 12, 6, '2026-06-05 15:00:00', 'Binh Thanh District', 'Heavy Rain', 60, 'Minutes'),
(15, 13, 7, '2026-06-06 10:00:00', 'Long Bien Bridge', 'Clear', 15, 'Minutes'),
(16, 14, 9, '2026-06-25 08:00:00', 'Java Sea', 'Clear', 0, 'Minutes'),
(17, 15, 10, '2026-05-30 14:00:00', 'Arabian Sea', 'Typhoon', 3, 'Days'),
(18, 16, 11, '2026-05-28 09:00:00', 'Salt Lake City', 'Clear', 0, 'Minutes'),
(19, 18, 15, '2026-05-25 16:00:00', 'Gulf of Guinea', 'Clear', 0, 'Minutes'),
(20, 20, 17, '2026-06-06 15:30:00', 'Taiwan Airspace', 'Typhoon', 5, 'Hours');

-- --------------------------------------------------------

--
-- Table structure for table `transport_asset`
--

CREATE TABLE `transport_asset` (
  `AssetID` int(11) NOT NULL,
  `CarrierID` int(11) DEFAULT NULL,
  `AssetCategory` varchar(50) DEFAULT NULL,
  `VehicleModel` varchar(100) DEFAULT NULL,
  `MaxWeight` decimal(10,2) DEFAULT NULL,
  `MaxVolume` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transport_asset`
--

INSERT INTO `transport_asset` (`AssetID`, `CarrierID`, `AssetCategory`, `VehicleModel`, `MaxWeight`, `MaxVolume`) VALUES
(1, 21, 'Vessel', 'Maersk Triple E Class (20,000 TEU)', 99999999.99, 400000.00),
(2, 22, 'Vessel', 'MSC Gulsun Class (23,000 TEU)', 99999999.99, 460000.00),
(3, 23, 'Vessel', 'CMA CGM Jacques Saadé (23,000 TEU)', 99999999.99, 460000.00),
(4, 24, 'Vessel', 'Hapag-Lloyd Hamburg Express Class', 99999999.99, 260000.00),
(5, 25, 'Vessel', 'COSCO Universe Class (21,000 TEU)', 99999999.99, 420000.00),
(6, 28, 'Aircraft', 'Boeing 777F Cargo', 102000.00, 650.00),
(7, 29, 'Aircraft', 'Boeing 747-8F', 137000.00, 850.00),
(8, 30, 'Aircraft', 'Airbus A330-200F', 70000.00, 475.00),
(9, 31, 'Aircraft', 'Boeing 777F Cargo (Emirates)', 102000.00, 650.00),
(10, 32, 'Aircraft', 'Boeing 747-400ERF', 112000.00, 710.00),
(11, 34, 'Train', 'Siemens Vectron Freight Locomotive', 2500000.00, 5000.00),
(12, 35, 'Train', 'Alstom Prima Freight Train', 2000000.00, 4500.00),
(13, 36, 'Truck', 'Hino 500 Series 15-Ton', 15000.00, 50.00),
(14, 37, 'Truck', 'Isuzu F-Series 8-Ton', 8000.00, 35.00),
(15, 38, 'Van', 'Ford Transit Cargo Van', 1500.00, 12.00),
(16, 39, 'Van', 'Mercedes-Benz Sprinter', 2000.00, 15.00),
(17, 40, 'Truck', 'Thaco Auman C160', 9000.00, 40.00),
(18, 28, 'Truck', 'Volvo FH16 Semi-Trailer', 40000.00, 100.00),
(19, 29, 'Truck', 'Scania R-Series Semi-Trailer', 40000.00, 100.00),
(20, 36, 'Motorbike', 'Honda Wave Alpha (Delivery Mod)', 150.00, 0.50);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse`
--

CREATE TABLE `warehouse` (
  `WarehouseID` int(11) NOT NULL,
  `LocationName` varchar(100) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `WarehouseType` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse`
--

INSERT INTO `warehouse` (`WarehouseID`, `LocationName`, `Address`, `WarehouseType`) VALUES
(1, 'ITL Noi Bai Hub', 'Phu Minh, Soc Son District, Hanoi, Vietnam', 'Bonded Warehouse'),
(2, 'ITL Green SGN', 'Song Than IP, Di An, Binh Duong, Vietnam', 'Distribution Center'),
(3, 'ITL Pacific LAX', '11222 Aviation Blvd, Los Angeles, CA 90045, USA', 'Cross-dock'),
(4, 'Frankfurt EuroHub', 'Cargo City Süd, 60549 Frankfurt am Main, Germany', 'Fulfillment Center'),
(5, 'Singapore Mega Hub', '10 Changi Coast Road, Singapore 499769', 'Distribution Center'),
(6, 'Dubai South Cargo', 'Al Maktoum Int Airport, Dubai, UAE', 'Cross-dock'),
(7, 'Shanghai Port DC', 'Pudong New Area, Shanghai, China', 'Distribution Center'),
(8, 'Rotterdam Gateway', 'Maasvlakte 2, Rotterdam, Netherlands', 'Bonded Warehouse'),
(9, 'ITL Da Nang Port', 'Tien Sa Port, Son Tra, Da Nang, Vietnam', 'Cross-dock'),
(10, 'Hai Phong Deep Sea', 'Dinh Vu IP, Hai Phong, Vietnam', 'Distribution Center'),
(11, 'Tokyo Narita Cargo', 'Narita Int Airport, Chiba, Japan', 'Fulfillment Center'),
(12, 'London Heathrow DC', 'Shoreham Road, Hounslow TW6 3UA, UK', 'Cross-dock'),
(13, 'Sydney Botany Bay', 'Port Botany, NSW 2036, Australia', 'Distribution Center'),
(14, 'Hong Kong Chek Lap', 'SuperTerminal 1, HKIA, Hong Kong', 'Cross-dock'),
(15, 'Seoul Incheon Hub', 'Incheon Int Airport, Jung-gu, South Korea', 'Fulfillment Center'),
(16, 'Mumbai Nhava Sheva', 'JNPT Road, Navi Mumbai, Maharashtra, India', 'Distribution Center'),
(17, 'ITL Can Tho Regional', 'Tra Noc IP, Binh Thuy, Can Tho, Vietnam', 'Local Hub'),
(18, 'ITL Bac Ninh fulfillment', 'Yen Phong IP, Bac Ninh, Vietnam', 'Fulfillment Center'),
(19, 'Chicago O\'Hare Cargo', '11601 W Touhy Ave, Chicago, IL 60666, USA', 'Cross-dock'),
(20, 'Toronto Pearson DC', '6300 Silver Dart Dr, Mississauga, ON, Canada', 'Distribution Center');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_zone`
--

CREATE TABLE `warehouse_zone` (
  `ZoneID` int(11) NOT NULL,
  `WarehouseID` int(11) DEFAULT NULL,
  `ItemID` int(11) DEFAULT NULL,
  `ZoneType` varchar(50) DEFAULT NULL,
  `Threshold` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse_zone`
--

INSERT INTO `warehouse_zone` (`ZoneID`, `WarehouseID`, `ItemID`, `ZoneType`, `Threshold`) VALUES
(1, 1, 1, 'Pallet Racking', 5000),
(2, 2, 2, 'Pallet Racking', 8000),
(3, 3, 3, 'Floor Storage', 2000),
(4, 4, 4, 'Shelving', 1500),
(5, 5, 5, 'Heavy Duty Racking', 500),
(6, 6, 6, 'Shelving', 10000),
(7, 7, 7, 'Shelving', 8500),
(8, 8, 8, 'Automated Retrieval', 12000),
(9, 9, 9, 'Automated Retrieval', 25000),
(10, 10, 10, 'Pallet Racking', 4000),
(11, 11, 11, 'Pallet Racking', 5500),
(12, 12, 12, 'Floor Storage', 3000),
(13, 13, 13, 'Floor Storage', 1000),
(14, 14, 14, 'Floor Storage', 1000),
(15, 15, 15, 'Outdoor Yard', 20000),
(16, 16, 16, 'Cold Storage', 6000),
(17, 17, 17, 'Secure Vault', 1500),
(18, 18, 18, 'Pallet Racking', 500),
(19, 19, 19, 'Secure Vault', 6000),
(20, 20, 20, 'Shelving', 1000);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account`
--
ALTER TABLE `account`
  ADD PRIMARY KEY (`AccountID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `EmployeeID` (`EmployeeID`),
  ADD KEY `RoleID` (`RoleID`);

--
-- Indexes for table `billing_structure`
--
ALTER TABLE `billing_structure`
  ADD PRIMARY KEY (`BillingID`);

--
-- Indexes for table `business_party`
--
ALTER TABLE `business_party`
  ADD PRIMARY KEY (`PartyID`);

--
-- Indexes for table `carrier`
--
ALTER TABLE `carrier`
  ADD PRIMARY KEY (`PartyID`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`PartyID`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD KEY `RoleID` (`RoleID`),
  ADD KEY `WarehouseID` (`WarehouseID`);

--
-- Indexes for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `ZoneID` (`ZoneID`),
  ADD KEY `SKUID` (`SKUID`),
  ADD KEY `AccountID` (`AccountID`);

--
-- Indexes for table `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`InvoiceID`),
  ADD KEY `OrderID` (`OrderID`),
  ADD KEY `BilledPartyID` (`BilledPartyID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `invoice_line`
--
ALTER TABLE `invoice_line`
  ADD PRIMARY KEY (`LineID`),
  ADD KEY `InvoiceID` (`InvoiceID`),
  ADD KEY `BillingID` (`BillingID`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`ItemID`),
  ADD KEY `SKUID` (`SKUID`),
  ADD KEY `ZoneID` (`ZoneID`),
  ADD KEY `OrderID` (`OrderID`);

--
-- Indexes for table `operational_exception`
--
ALTER TABLE `operational_exception`
  ADD PRIMARY KEY (`ExceptionID`),
  ADD KEY `CarrierID` (`CarrierID`),
  ADD KEY `ShipmentID` (`ShipmentID`),
  ADD KEY `AccountID` (`AccountID`);

--
-- Indexes for table `order_detailed`
--
ALTER TABLE `order_detailed`
  ADD PRIMARY KEY (`OrderID`,`SKUID`),
  ADD KEY `SKUID` (`SKUID`);

--
-- Indexes for table `order_info`
--
ALTER TABLE `order_info`
  ADD PRIMARY KEY (`OrderID`),
  ADD KEY `AccountID` (`AccountID`),
  ADD KEY `CustomerID` (`CustomerID`);

--
-- Indexes for table `payment_transaction`
--
ALTER TABLE `payment_transaction`
  ADD PRIMARY KEY (`PaymentID`),
  ADD KEY `InvoiceID` (`InvoiceID`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`RoleID`);

--
-- Indexes for table `route`
--
ALTER TABLE `route`
  ADD PRIMARY KEY (`RouteID`);

--
-- Indexes for table `shipment`
--
ALTER TABLE `shipment`
  ADD PRIMARY KEY (`ShipmentID`),
  ADD KEY `RouteID` (`RouteID`),
  ADD KEY `AssetID` (`AssetID`);

--
-- Indexes for table `shipment_order`
--
ALTER TABLE `shipment_order`
  ADD PRIMARY KEY (`ShipmentID`,`OrderID`),
  ADD KEY `OrderID` (`OrderID`);

--
-- Indexes for table `sku`
--
ALTER TABLE `sku`
  ADD PRIMARY KEY (`SKUID`);

--
-- Indexes for table `system_audit_log`
--
ALTER TABLE `system_audit_log`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `AccountID` (`AccountID`);

--
-- Indexes for table `system_notification`
--
ALTER TABLE `system_notification`
  ADD PRIMARY KEY (`NotifID`),
  ADD KEY `AccountID` (`AccountID`);

--
-- Indexes for table `tracking_log`
--
ALTER TABLE `tracking_log`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `ShipmentID` (`ShipmentID`),
  ADD KEY `AccountID` (`AccountID`);

--
-- Indexes for table `transport_asset`
--
ALTER TABLE `transport_asset`
  ADD PRIMARY KEY (`AssetID`),
  ADD KEY `CarrierID` (`CarrierID`);

--
-- Indexes for table `warehouse`
--
ALTER TABLE `warehouse`
  ADD PRIMARY KEY (`WarehouseID`);

--
-- Indexes for table `warehouse_zone`
--
ALTER TABLE `warehouse_zone`
  ADD PRIMARY KEY (`ZoneID`),
  ADD KEY `WarehouseID` (`WarehouseID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account`
--
ALTER TABLE `account`
  MODIFY `AccountID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `billing_structure`
--
ALTER TABLE `billing_structure`
  MODIFY `BillingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `business_party`
--
ALTER TABLE `business_party`
  MODIFY `PartyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `inventory_log`
--
ALTER TABLE `inventory_log`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `InvoiceID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `invoice_line`
--
ALTER TABLE `invoice_line`
  MODIFY `LineID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `ItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `operational_exception`
--
ALTER TABLE `operational_exception`
  MODIFY `ExceptionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `order_info`
--
ALTER TABLE `order_info`
  MODIFY `OrderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payment_transaction`
--
ALTER TABLE `payment_transaction`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `RoleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `route`
--
ALTER TABLE `route`
  MODIFY `RouteID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `shipment`
--
ALTER TABLE `shipment`
  MODIFY `ShipmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `sku`
--
ALTER TABLE `sku`
  MODIFY `SKUID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `system_audit_log`
--
ALTER TABLE `system_audit_log`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `system_notification`
--
ALTER TABLE `system_notification`
  MODIFY `NotifID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tracking_log`
--
ALTER TABLE `tracking_log`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `transport_asset`
--
ALTER TABLE `transport_asset`
  MODIFY `AssetID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `warehouse`
--
ALTER TABLE `warehouse`
  MODIFY `WarehouseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `warehouse_zone`
--
ALTER TABLE `warehouse_zone`
  MODIFY `ZoneID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account`
--
ALTER TABLE `account`
  ADD CONSTRAINT `account_ibfk_1` FOREIGN KEY (`EmployeeID`) REFERENCES `employee` (`EmployeeID`),
  ADD CONSTRAINT `account_ibfk_2` FOREIGN KEY (`RoleID`) REFERENCES `role` (`RoleID`);

--
-- Constraints for table `carrier`
--
ALTER TABLE `carrier`
  ADD CONSTRAINT `carrier_ibfk_1` FOREIGN KEY (`PartyID`) REFERENCES `business_party` (`PartyID`) ON DELETE CASCADE;

--
-- Constraints for table `customer`
--
ALTER TABLE `customer`
  ADD CONSTRAINT `customer_ibfk_1` FOREIGN KEY (`PartyID`) REFERENCES `business_party` (`PartyID`) ON DELETE CASCADE;

--
-- Constraints for table `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`RoleID`) REFERENCES `role` (`RoleID`),
  ADD CONSTRAINT `employee_ibfk_2` FOREIGN KEY (`WarehouseID`) REFERENCES `warehouse` (`WarehouseID`);

--
-- Constraints for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD CONSTRAINT `inventory_log_ibfk_1` FOREIGN KEY (`ZoneID`) REFERENCES `warehouse_zone` (`ZoneID`),
  ADD CONSTRAINT `inventory_log_ibfk_2` FOREIGN KEY (`SKUID`) REFERENCES `sku` (`SKUID`),
  ADD CONSTRAINT `inventory_log_ibfk_3` FOREIGN KEY (`AccountID`) REFERENCES `account` (`AccountID`);

--
-- Constraints for table `invoice`
--
ALTER TABLE `invoice`
  ADD CONSTRAINT `invoice_ibfk_1` FOREIGN KEY (`OrderID`) REFERENCES `order_info` (`OrderID`),
  ADD CONSTRAINT `invoice_ibfk_2` FOREIGN KEY (`BilledPartyID`) REFERENCES `business_party` (`PartyID`),
  ADD CONSTRAINT `invoice_ibfk_3` FOREIGN KEY (`UserID`) REFERENCES `account` (`AccountID`);

--
-- Constraints for table `invoice_line`
--
ALTER TABLE `invoice_line`
  ADD CONSTRAINT `invoice_line_ibfk_1` FOREIGN KEY (`InvoiceID`) REFERENCES `invoice` (`InvoiceID`),
  ADD CONSTRAINT `invoice_line_ibfk_2` FOREIGN KEY (`BillingID`) REFERENCES `billing_structure` (`BillingID`);

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`SKUID`) REFERENCES `sku` (`SKUID`),
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`ZoneID`) REFERENCES `warehouse_zone` (`ZoneID`),
  ADD CONSTRAINT `items_ibfk_3` FOREIGN KEY (`OrderID`) REFERENCES `order_info` (`OrderID`);

--
-- Constraints for table `operational_exception`
--
ALTER TABLE `operational_exception`
  ADD CONSTRAINT `operational_exception_ibfk_1` FOREIGN KEY (`CarrierID`) REFERENCES `carrier` (`PartyID`),
  ADD CONSTRAINT `operational_exception_ibfk_2` FOREIGN KEY (`ShipmentID`) REFERENCES `shipment` (`ShipmentID`),
  ADD CONSTRAINT `operational_exception_ibfk_3` FOREIGN KEY (`AccountID`) REFERENCES `account` (`AccountID`);

--
-- Constraints for table `order_detailed`
--
ALTER TABLE `order_detailed`
  ADD CONSTRAINT `order_detailed_ibfk_1` FOREIGN KEY (`OrderID`) REFERENCES `order_info` (`OrderID`),
  ADD CONSTRAINT `order_detailed_ibfk_2` FOREIGN KEY (`SKUID`) REFERENCES `sku` (`SKUID`);

--
-- Constraints for table `order_info`
--
ALTER TABLE `order_info`
  ADD CONSTRAINT `order_info_ibfk_1` FOREIGN KEY (`AccountID`) REFERENCES `account` (`AccountID`),
  ADD CONSTRAINT `order_info_ibfk_2` FOREIGN KEY (`CustomerID`) REFERENCES `customer` (`PartyID`);

--
-- Constraints for table `payment_transaction`
--
ALTER TABLE `payment_transaction`
  ADD CONSTRAINT `payment_transaction_ibfk_1` FOREIGN KEY (`InvoiceID`) REFERENCES `invoice` (`InvoiceID`);

--
-- Constraints for table `shipment`
--
ALTER TABLE `shipment`
  ADD CONSTRAINT `shipment_ibfk_1` FOREIGN KEY (`RouteID`) REFERENCES `route` (`RouteID`),
  ADD CONSTRAINT `shipment_ibfk_2` FOREIGN KEY (`AssetID`) REFERENCES `transport_asset` (`AssetID`);

--
-- Constraints for table `shipment_order`
--
ALTER TABLE `shipment_order`
  ADD CONSTRAINT `shipment_order_ibfk_1` FOREIGN KEY (`ShipmentID`) REFERENCES `shipment` (`ShipmentID`),
  ADD CONSTRAINT `shipment_order_ibfk_2` FOREIGN KEY (`OrderID`) REFERENCES `order_info` (`OrderID`);

--
-- Constraints for table `system_audit_log`
--
ALTER TABLE `system_audit_log`
  ADD CONSTRAINT `system_audit_log_ibfk_1` FOREIGN KEY (`AccountID`) REFERENCES `account` (`AccountID`);

--
-- Constraints for table `system_notification`
--
ALTER TABLE `system_notification`
  ADD CONSTRAINT `system_notification_ibfk_1` FOREIGN KEY (`AccountID`) REFERENCES `account` (`AccountID`);

--
-- Constraints for table `tracking_log`
--
ALTER TABLE `tracking_log`
  ADD CONSTRAINT `tracking_log_ibfk_1` FOREIGN KEY (`ShipmentID`) REFERENCES `shipment` (`ShipmentID`),
  ADD CONSTRAINT `tracking_log_ibfk_2` FOREIGN KEY (`AccountID`) REFERENCES `account` (`AccountID`);

--
-- Constraints for table `transport_asset`
--
ALTER TABLE `transport_asset`
  ADD CONSTRAINT `transport_asset_ibfk_1` FOREIGN KEY (`CarrierID`) REFERENCES `carrier` (`PartyID`);

--
-- Constraints for table `warehouse_zone`
--
ALTER TABLE `warehouse_zone`
  ADD CONSTRAINT `warehouse_zone_ibfk_1` FOREIGN KEY (`WarehouseID`) REFERENCES `warehouse` (`WarehouseID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
