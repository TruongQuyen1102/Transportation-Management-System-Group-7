<?php
// ══════════════════════════════════════════════════════════════════════════════
//  sample_data.php  —  ALL mock data for LogiTrack Pro
//  Replace each function body with a DB query when connecting to MySQL/XAMPP
// ══════════════════════════════════════════════════════════════════════════════

// ─── Demo credentials ─────────────────────────────────────────────────────────
function get_demo_accounts(): array {
    return [
        ['id'=>'ACC001','username'=>'admin',      'password'=>'admin123',      'role'=>'admin',      'name'=>'Alex Administrator', 'email'=>'admin@logitrack.com',    'avatar'=>'AA','status'=>'active'],
        ['id'=>'ACC002','username'=>'manager',    'password'=>'manager123',    'role'=>'manager',    'name'=>'Maria Chen',         'email'=>'manager@logitrack.com',  'avatar'=>'MC','status'=>'active'],
        ['id'=>'ACC003','username'=>'accountant', 'password'=>'accountant123', 'role'=>'accountant', 'name'=>'James Pham',         'email'=>'accountant@logitrack.com','avatar'=>'JP','status'=>'active'],
        ['id'=>'ACC004','username'=>'ops',        'password'=>'ops123',        'role'=>'operations', 'name'=>'Olivia Nguyen',      'email'=>'ops@logitrack.com',      'avatar'=>'ON','status'=>'active'],
        ['id'=>'ACC005','username'=>'ops2',       'password'=>'ops123',        'role'=>'operations', 'name'=>'Kevin Tran',         'email'=>'ops2@logitrack.com',     'avatar'=>'KT','status'=>'active'],
        ['id'=>'ACC006','username'=>'manager2',   'password'=>'manager123',    'role'=>'manager',    'name'=>'Sandra Lee',         'email'=>'manager2@logitrack.com', 'avatar'=>'SL','status'=>'inactive'],
    ];
}

// ─── Customers ────────────────────────────────────────────────────────────────
function get_customers(): array {
    return [
        ['id'=>'CUST001','name'=>'Saigon Textile Corp.',       'type'=>'Enterprise','tax'=>'0301234567','industry'=>'Manufacturing',  'email'=>'logistics@sgtextile.vn',  'phone'=>'+84 28 3823 0001','address'=>'12 Nguyen Hue, District 1, HCMC'],
        ['id'=>'CUST002','name'=>'Hanoi Electronics Ltd.',     'type'=>'Enterprise','tax'=>'0100987654','industry'=>'Electronics',    'email'=>'supply@hanoitech.com.vn', 'phone'=>'+84 24 3941 0022','address'=>'88 Tran Duy Hung, Cau Giay, Hanoi'],
        ['id'=>'CUST003','name'=>'FreshMart Distribution',     'type'=>'SME',       'tax'=>'0312345678','industry'=>'FMCG',           'email'=>'ops@freshmart.vn',        'phone'=>'+84 28 3912 3434','address'=>'45 Le Van Viet, District 9, HCMC'],
        ['id'=>'CUST004','name'=>'Pacific Pharma Group',       'type'=>'Enterprise','tax'=>'0303456789','industry'=>'Pharmaceutical', 'email'=>'chain@pacificpharma.vn',  'phone'=>'+84 28 5412 8800','address'=>'200 Nguyen Thi Minh Khai, D3, HCMC'],
        ['id'=>'CUST005','name'=>'Mekong Agri Exports',        'type'=>'SME',       'tax'=>'1800567890','industry'=>'Agriculture',    'email'=>'export@mekongagri.vn',    'phone'=>'+84 710 382 4455','address'=>'Long Xuyen, An Giang Province'],
    ];
}

// ─── Carriers ─────────────────────────────────────────────────────────────────
function get_carriers(): array {
    return [
        ['id'=>'CAR001','name'=>'VietSpeed Logistics',   'email'=>'ops@vietspeed.vn',  'phone'=>'+84 28 3830 1111','pfm_score'=>92,'capabilities'=>'FTL,LTL,Express','service_area'=>'National','status'=>'active', 'note'=>'Primary carrier for HCMC–Hanoi corridor'],
        ['id'=>'CAR002','name'=>'MekongShip Transport',  'email'=>'dispatch@mekong.vn','phone'=>'+84 710 383 2222','pfm_score'=>87,'capabilities'=>'LTL,Refrigerated','service_area'=>'South VN','status'=>'active', 'note'=>'Specialises in cold-chain for Mekong delta'],
        ['id'=>'CAR003','name'=>'HighlandEx Freight',    'email'=>'hq@highlandex.vn', 'phone'=>'+84 263 382 3333','pfm_score'=>78,'capabilities'=>'FTL,Bulk,Oversize','service_area'=>'Central & Highland','status'=>'active', 'note'=>'Best for oversized cargo to Central region'],
        ['id'=>'CAR004','name'=>'SwiftAir Cargo VN',     'email'=>'cargo@swiftair.vn','phone'=>'+84 24 3822 4444','pfm_score'=>95,'capabilities'=>'Air,Express','service_area'=>'National+International','status'=>'active', 'note'=>'Air freight partner for urgent pharma'],
        ['id'=>'CAR005','name'=>'BlueStar Shipping Co.', 'email'=>'ops@bluestar.vn',  'phone'=>'+84 28 3890 5555','pfm_score'=>65,'capabilities'=>'FCL,LCL,Sea','service_area'=>'International','status'=>'inactive','note'=>'On hold pending contract renewal'],
    ];
}

// ─── Warehouses ───────────────────────────────────────────────────────────────
function get_warehouses(): array {
    return [
        ['id'=>'WH001','name'=>'HCMC Central Hub',    'address'=>'Binh Duong Industrial Park, Binh Duong','type'=>'Hub',         'zones'=>4],
        ['id'=>'WH002','name'=>'Hanoi North Depot',   'address'=>'Noi Bai Logistics Zone, Hanoi',         'type'=>'Distribution','zones'=>3],
        ['id'=>'WH003','name'=>'Da Nang Port Depot',  'address'=>'Son Tra Port Area, Da Nang',            'type'=>'Cross-dock',  'zones'=>2],
    ];
}

// ─── SKUs ─────────────────────────────────────────────────────────────────────
function get_skus(): array {
    return [
        ['id'=>'SKU001','name'=>'Premium Cotton Fabric Roll',  'uom'=>'Roll',  'qty'=>320, 'stock'=>'Adequate'],
        ['id'=>'SKU002','name'=>'Electronic PCB Board A4',      'uom'=>'Unit',  'qty'=>1850,'stock'=>'Adequate'],
        ['id'=>'SKU003','name'=>'Fresh Produce Crate 20kg',     'uom'=>'Crate', 'qty'=>240, 'stock'=>'Low'],
        ['id'=>'SKU004','name'=>'Pharmaceutical Vial Box',      'uom'=>'Box',   'qty'=>900, 'stock'=>'Adequate'],
        ['id'=>'SKU005','name'=>'Rice Bag 50kg',                'uom'=>'Bag',   'qty'=>1200,'stock'=>'Adequate'],
        ['id'=>'SKU006','name'=>'Industrial Motor Unit',        'uom'=>'Unit',  'qty'=>45,  'stock'=>'Low'],
        ['id'=>'SKU007','name'=>'Garment Carton (Assorted)',    'uom'=>'Carton','qty'=>680, 'stock'=>'Adequate'],
        ['id'=>'SKU008','name'=>'Chemical Drum 200L',           'uom'=>'Drum',  'qty'=>88,  'stock'=>'Low'],
        ['id'=>'SKU009','name'=>'Laptop Retail Box',            'uom'=>'Unit',  'qty'=>430, 'stock'=>'Adequate'],
        ['id'=>'SKU010','name'=>'Cold-chain Vaccine Pack',      'uom'=>'Pack',  'qty'=>2200,'stock'=>'Adequate'],
    ];
}

// ─── Transport Assets ─────────────────────────────────────────────────────────
function get_transport_assets(): array {
    return [
        ['id'=>'AST001','carrier_id'=>'CAR001','carrier'=>'VietSpeed Logistics',  'mode'=>'Road','type'=>'40ft Container Truck','plate'=>'51C-12345','max_weight'=>20000,'max_volume'=>67.0,'status'=>'Available'],
        ['id'=>'AST002','carrier_id'=>'CAR001','carrier'=>'VietSpeed Logistics',  'mode'=>'Road','type'=>'20ft Box Truck',      'plate'=>'51C-23456','max_weight'=>8000, 'max_volume'=>33.0,'status'=>'In Use'],
        ['id'=>'AST003','carrier_id'=>'CAR002','carrier'=>'MekongShip Transport', 'mode'=>'Road','type'=>'Refrigerated Van',    'plate'=>'65A-34567','max_weight'=>3500, 'max_volume'=>14.0,'status'=>'Available'],
        ['id'=>'AST004','carrier_id'=>'CAR002','carrier'=>'MekongShip Transport', 'mode'=>'Waterway','type'=>'River Barge',    'plate'=>'MKG-45678','max_weight'=>50000,'max_volume'=>200.0,'status'=>'In Use'],
        ['id'=>'AST005','carrier_id'=>'CAR003','carrier'=>'HighlandEx Freight',   'mode'=>'Road','type'=>'Flatbed Trailer',    'plate'=>'47A-56789','max_weight'=>25000,'max_volume'=>80.0,'status'=>'Maintenance'],
        ['id'=>'AST006','carrier_id'=>'CAR004','carrier'=>'SwiftAir Cargo VN',    'mode'=>'Air', 'type'=>'Air Cargo Slot',     'plate'=>'VN-AIR-01','max_weight'=>2000, 'max_volume'=>10.0,'status'=>'Available'],
        ['id'=>'AST007','carrier_id'=>'CAR001','carrier'=>'VietSpeed Logistics',  'mode'=>'Road','type'=>'Curtainsider Truck', 'plate'=>'51B-67890','max_weight'=>15000,'max_volume'=>90.0,'status'=>'Available'],
    ];
}

// ─── Routes ───────────────────────────────────────────────────────────────────
function get_routes(): array {
    return [
        ['id'=>'RTE001','name'=>'HCMC → Hanoi Express',      'start'=>'HCMC Central Hub',  'end'=>'Hanoi North Depot','distance'=>1726,'duration'=>36,'unit'=>'hours','cost_base'=>8500000],
        ['id'=>'RTE002','name'=>'HCMC → Da Nang Standard',   'start'=>'HCMC Central Hub',  'end'=>'Da Nang Port Depot','distance'=>964,'duration'=>18,'unit'=>'hours','cost_base'=>4200000],
        ['id'=>'RTE003','name'=>'Hanoi → Da Nang',           'start'=>'Hanoi North Depot', 'end'=>'Da Nang Port Depot','distance'=>764,'duration'=>14,'unit'=>'hours','cost_base'=>3600000],
        ['id'=>'RTE004','name'=>'HCMC → Can Tho Local',      'start'=>'HCMC Central Hub',  'end'=>'Can Tho City',     'distance'=>170,'duration'=>3.5,'unit'=>'hours','cost_base'=>850000],
        ['id'=>'RTE005','name'=>'HCMC → Vung Tau Coastal',   'start'=>'HCMC Central Hub',  'end'=>'Vung Tau City',    'distance'=>125,'duration'=>2,'unit'=>'hours','cost_base'=>620000],
        ['id'=>'RTE006','name'=>'Hanoi → Ha Long Bay',       'start'=>'Hanoi North Depot', 'end'=>'Ha Long City',     'distance'=>165,'duration'=>3,'unit'=>'hours','cost_base'=>780000],
        ['id'=>'RTE007','name'=>'Da Nang → Hue Heritage',    'start'=>'Da Nang Port Depot','end'=>'Hue City',         'distance'=>99, 'duration'=>2,'unit'=>'hours','cost_base'=>480000],
        ['id'=>'RTE008','name'=>'Air Express HAN → SGN',     'start'=>'Noi Bai Airport',   'end'=>'Tan Son Nhat Apt', 'distance'=>1140,'duration'=>2,'unit'=>'hours','cost_base'=>15000000],
    ];
}

// ─── Orders ───────────────────────────────────────────────────────────────────
function get_orders(): array {
    return [
        ['id'=>'ORD001','customer_id'=>'CUST001','customer'=>'Saigon Textile Corp.',   'pickup'=>'12 Nguyen Hue, D1, HCMC','delivery'=>'88 Tran Duy Hung, Hanoi','order_date'=>'2025-05-10','expected_delivery'=>'2025-05-14','status'=>'DELIVERED','weight'=>3200,'volume'=>18.5,'sku_count'=>2],
        ['id'=>'ORD002','customer_id'=>'CUST002','customer'=>'Hanoi Electronics Ltd.',  'pickup'=>'Binh Duong IZ, Binh Duong','delivery'=>'88 Tran Duy Hung, Hanoi','order_date'=>'2025-05-15','expected_delivery'=>'2025-05-20','status'=>'IN_TRANSIT','weight'=>1850,'volume'=>9.2,'sku_count'=>1],
        ['id'=>'ORD003','customer_id'=>'CUST003','customer'=>'FreshMart Distribution',  'pickup'=>'45 Le Van Viet, D9, HCMC', 'delivery'=>'Can Tho City Market',   'order_date'=>'2025-05-18','expected_delivery'=>'2025-05-19','status'=>'DELIVERED','weight'=>480,'volume'=>3.6,'sku_count'=>1],
        ['id'=>'ORD004','customer_id'=>'CUST004','customer'=>'Pacific Pharma Group',    'pickup'=>'200 NTM Khai, D3, HCMC',  'delivery'=>'Da Nang Medical Hub',   'order_date'=>'2025-05-20','expected_delivery'=>'2025-05-22','status'=>'IN_TRANSIT','weight'=>220,'volume'=>1.2,'sku_count'=>2],
        ['id'=>'ORD005','customer_id'=>'CUST005','customer'=>'Mekong Agri Exports',     'pickup'=>'Long Xuyen, An Giang',    'delivery'=>'Can Tho Port',          'order_date'=>'2025-05-21','expected_delivery'=>'2025-05-22','status'=>'CONFIRMED','weight'=>6000,'volume'=>40.0,'sku_count'=>1],
        ['id'=>'ORD006','customer_id'=>'CUST001','customer'=>'Saigon Textile Corp.',    'pickup'=>'12 Nguyen Hue, D1, HCMC','delivery'=>'Vung Tau Export Zone',  'order_date'=>'2025-05-22','expected_delivery'=>'2025-05-24','status'=>'PENDING','weight'=>800,'volume'=>5.0,'sku_count'=>1],
        ['id'=>'ORD007','customer_id'=>'CUST002','customer'=>'Hanoi Electronics Ltd.',  'pickup'=>'Noi Bai IZ, Hanoi',       'delivery'=>'Da Nang Tech Park',     'order_date'=>'2025-05-23','expected_delivery'=>'2025-05-26','status'=>'PENDING','weight'=>1200,'volume'=>7.8,'sku_count'=>2],
        ['id'=>'ORD008','customer_id'=>'CUST004','customer'=>'Pacific Pharma Group',    'pickup'=>'200 NTM Khai, D3, HCMC',  'delivery'=>'Hanoi Medical Store',   'order_date'=>'2025-05-24','expected_delivery'=>'2025-05-25','status'=>'IN_TRANSIT','weight'=>180,'volume'=>0.9,'sku_count'=>1],
        ['id'=>'ORD009','customer_id'=>'CUST003','customer'=>'FreshMart Distribution',  'pickup'=>'45 Le Van Viet, D9, HCMC','delivery'=>'Big C Thu Duc, HCMC',   'order_date'=>'2025-05-25','expected_delivery'=>'2025-05-25','status'=>'DELIVERED','weight'=>320,'volume'=>2.4,'sku_count'=>1],
        ['id'=>'ORD010','customer_id'=>'CUST005','customer'=>'Mekong Agri Exports',     'pickup'=>'Long Xuyen, An Giang',    'delivery'=>'Ben Tre Province',      'order_date'=>'2025-05-26','expected_delivery'=>'2025-05-27','status'=>'CANCELLED','weight'=>4500,'volume'=>30.0,'sku_count'=>1],
        ['id'=>'ORD011','customer_id'=>'CUST001','customer'=>'Saigon Textile Corp.',    'pickup'=>'Binh Duong IZ, Binh Duong','delivery'=>'Hue City Depot',       'order_date'=>'2025-05-27','expected_delivery'=>'2025-05-30','status'=>'CONFIRMED','weight'=>2100,'volume'=>14.0,'sku_count'=>2],
        ['id'=>'ORD012','customer_id'=>'CUST002','customer'=>'Hanoi Electronics Ltd.',  'pickup'=>'Noi Bai IZ, Hanoi',       'delivery'=>'HCMC Central Hub',      'order_date'=>'2025-05-28','expected_delivery'=>'2025-06-02','status'=>'PENDING','weight'=>950,'volume'=>6.0,'sku_count'=>1],
    ];
}

// ─── Shipments ────────────────────────────────────────────────────────────────
function get_shipments(): array {
    return [
        ['id'=>'SHP001','order_id'=>'ORD001','route_id'=>'RTE001','route'=>'HCMC → Hanoi Express',    'asset_id'=>'AST001','asset'=>'40ft Container Truck (51C-12345)','carrier'=>'VietSpeed Logistics', 'status'=>'DELIVERED','planned_dep'=>'2025-05-10 08:00','actual_dep'=>'2025-05-10 08:30','est_arr'=>'2025-05-12 20:00','actual_arr'=>'2025-05-12 22:15','deadline'=>'2025-05-14'],
        ['id'=>'SHP002','order_id'=>'ORD002','route_id'=>'RTE001','route'=>'HCMC → Hanoi Express',    'asset_id'=>'AST007','asset'=>'Curtainsider Truck (51B-67890)',   'carrier'=>'VietSpeed Logistics', 'status'=>'IN_TRANSIT','planned_dep'=>'2025-05-15 09:00','actual_dep'=>'2025-05-15 09:00','est_arr'=>'2025-05-17 21:00','actual_arr'=>null,'deadline'=>'2025-05-20'],
        ['id'=>'SHP003','order_id'=>'ORD003','route_id'=>'RTE004','route'=>'HCMC → Can Tho Local',    'asset_id'=>'AST003','asset'=>'Refrigerated Van (65A-34567)',      'carrier'=>'MekongShip Transport','status'=>'DELIVERED','planned_dep'=>'2025-05-18 06:00','actual_dep'=>'2025-05-18 06:15','est_arr'=>'2025-05-18 09:30','actual_arr'=>'2025-05-18 09:45','deadline'=>'2025-05-19'],
        ['id'=>'SHP004','order_id'=>'ORD004','route_id'=>'RTE002','route'=>'HCMC → Da Nang Standard', 'asset_id'=>'AST002','asset'=>'20ft Box Truck (51C-23456)',        'carrier'=>'VietSpeed Logistics', 'status'=>'IN_TRANSIT','planned_dep'=>'2025-05-20 07:00','actual_dep'=>'2025-05-20 07:30','est_arr'=>'2025-05-21 01:00','actual_arr'=>null,'deadline'=>'2025-05-22'],
        ['id'=>'SHP005','order_id'=>'ORD005','route_id'=>'RTE004','route'=>'HCMC → Can Tho Local',    'asset_id'=>'AST004','asset'=>'River Barge (MKG-45678)',           'carrier'=>'MekongShip Transport','status'=>'SCHEDULED','planned_dep'=>'2025-05-21 10:00','actual_dep'=>null,'est_arr'=>'2025-05-21 14:00','actual_arr'=>null,'deadline'=>'2025-05-22'],
        ['id'=>'SHP006','order_id'=>'ORD008','route_id'=>'RTE008','route'=>'Air Express HAN → SGN',   'asset_id'=>'AST006','asset'=>'Air Cargo Slot (VN-AIR-01)',        'carrier'=>'SwiftAir Cargo VN',   'status'=>'IN_TRANSIT','planned_dep'=>'2025-05-24 14:00','actual_dep'=>'2025-05-24 14:00','est_arr'=>'2025-05-24 16:00','actual_arr'=>null,'deadline'=>'2025-05-25'],
        ['id'=>'SHP007','order_id'=>'ORD009','route_id'=>'RTE004','route'=>'HCMC → Can Tho Local',    'asset_id'=>'AST003','asset'=>'Refrigerated Van (65A-34567)',      'carrier'=>'MekongShip Transport','status'=>'DELIVERED','planned_dep'=>'2025-05-25 05:00','actual_dep'=>'2025-05-25 05:00','est_arr'=>'2025-05-25 08:30','actual_arr'=>'2025-05-25 08:45','deadline'=>'2025-05-25'],
    ];
}

// ─── Tracking Logs ────────────────────────────────────────────────────────────
function get_tracking_logs(): array {
    return [
        ['id'=>'TRK001','shipment_id'=>'SHP001','account'=>'Olivia Nguyen','timestamp'=>'2025-05-10 08:30','location'=>'HCMC Central Hub – Gate 3','weather'=>'Clear','delay'=>0,   'note'=>'Cargo loaded and departed on schedule'],
        ['id'=>'TRK002','shipment_id'=>'SHP001','account'=>'Olivia Nguyen','timestamp'=>'2025-05-11 06:00','location'=>'Binh Thuan Rest Stop, Km 220','weather'=>'Light Rain','delay'=>15,'note'=>'Brief delay due to rain; back on route'],
        ['id'=>'TRK003','shipment_id'=>'SHP001','account'=>'Kevin Tran',  'timestamp'=>'2025-05-12 22:15','location'=>'Hanoi North Depot – Receiving Bay','weather'=>'Clear','delay'=>135,'note'=>'Delivered; slight delay from Hanoi traffic'],
        ['id'=>'TRK004','shipment_id'=>'SHP002','account'=>'Olivia Nguyen','timestamp'=>'2025-05-15 09:00','location'=>'HCMC Central Hub – Gate 1','weather'=>'Clear','delay'=>0,   'note'=>'Departed on time'],
        ['id'=>'TRK005','shipment_id'=>'SHP002','account'=>'Kevin Tran',  'timestamp'=>'2025-05-16 14:00','location'=>'Binh Dinh Province Checkpoint','weather'=>'Cloudy','delay'=>0,'note'=>'Transit checkpoint cleared'],
        ['id'=>'TRK006','shipment_id'=>'SHP003','account'=>'Kevin Tran',  'timestamp'=>'2025-05-18 06:15','location'=>'HCMC Central Hub Depot','weather'=>'Sunny','delay'=>0,   'note'=>'Cold-chain cargo loaded at 4°C'],
        ['id'=>'TRK007','shipment_id'=>'SHP003','account'=>'Kevin Tran',  'timestamp'=>'2025-05-18 09:45','location'=>'Can Tho City Market Hub','weather'=>'Sunny','delay'=>15,  'note'=>'Minor traffic at Cai Lay junction'],
        ['id'=>'TRK008','shipment_id'=>'SHP004','account'=>'Olivia Nguyen','timestamp'=>'2025-05-20 07:30','location'=>'HCMC Central Hub','weather'=>'Clear','delay'=>30,  'note'=>'Slight departure delay; driver handover'],
        ['id'=>'TRK009','shipment_id'=>'SHP006','account'=>'Olivia Nguyen','timestamp'=>'2025-05-24 14:00','location'=>'Noi Bai Airport – Cargo Terminal','weather'=>'Clear','delay'=>0,'note'=>'Air cargo checked in, on-time departure'],
    ];
}

// ─── Operational Exceptions ───────────────────────────────────────────────────
function get_exceptions(): array {
    return [
        ['id'=>'EXC001','shipment_id'=>'SHP001','carrier'=>'VietSpeed Logistics','account'=>'Olivia Nguyen','type'=>'Traffic Delay','desc'=>'Heavy traffic on QL1A near Binh Dinh; 2-hour delay expected.','status'=>'RESOLVED','created_at'=>'2025-05-11 08:00'],
        ['id'=>'EXC002','shipment_id'=>'SHP002','carrier'=>'VietSpeed Logistics','account'=>'Kevin Tran',  'type'=>'Damaged Goods','desc'=>'3 cartons of PCB boards show moisture damage. Customer notified.','status'=>'OPEN','created_at'=>'2025-05-16 16:00'],
        ['id'=>'EXC003','shipment_id'=>'SHP004','carrier'=>'VietSpeed Logistics','account'=>'Olivia Nguyen','type'=>'Route Deviation','desc'=>'QL1 section near Quang Ngai closed for road works; rerouting via CT04.','status'=>'IN_REVIEW','created_at'=>'2025-05-20 11:00'],
        ['id'=>'EXC004','shipment_id'=>'SHP005','carrier'=>'MekongShip Transport','account'=>'Kevin Tran', 'type'=>'Vehicle Breakdown','desc'=>'River barge engine fault at Vinh Long. Requesting replacement vessel.','status'=>'OPEN','created_at'=>'2025-05-21 13:30'],
        ['id'=>'EXC005','shipment_id'=>'SHP006','carrier'=>'SwiftAir Cargo VN',  'account'=>'Olivia Nguyen','type'=>'Failed Delivery','desc'=>'Consignee warehouse closed at time of delivery; rescheduling required.','status'=>'RESOLVED','created_at'=>'2025-05-24 17:00'],
    ];
}

// ─── Invoices ─────────────────────────────────────────────────────────────────
function get_invoices(): array {
    return [
        ['id'=>'INV001','order_id'=>'ORD001','customer'=>'Saigon Textile Corp.',  'issue_date'=>'2025-05-13','due_date'=>'2025-06-13','currency'=>'VND','total'=>42500000, 'tax'=>10,'final'=>46750000,'status'=>'PAID',   'paid_date'=>'2025-05-28','ref'=>'TXN-2025-0528-001'],
        ['id'=>'INV002','order_id'=>'ORD002','customer'=>'Hanoi Electronics Ltd.','issue_date'=>'2025-05-18','due_date'=>'2025-06-18','currency'=>'VND','total'=>28000000, 'tax'=>10,'final'=>30800000,'status'=>'ISSUED', 'paid_date'=>null,'ref'=>null],
        ['id'=>'INV003','order_id'=>'ORD003','customer'=>'FreshMart Distribution','issue_date'=>'2025-05-18','due_date'=>'2025-06-18','currency'=>'VND','total'=>6800000,  'tax'=>10,'final'=>7480000, 'status'=>'PAID',   'paid_date'=>'2025-05-20','ref'=>'TXN-2025-0520-001'],
        ['id'=>'INV004','order_id'=>'ORD004','customer'=>'Pacific Pharma Group',  'issue_date'=>'2025-05-22','due_date'=>'2025-06-22','currency'=>'USD','total'=>3200,     'tax'=>10,'final'=>3520,    'status'=>'ISSUED', 'paid_date'=>null,'ref'=>null],
        ['id'=>'INV005','order_id'=>'ORD005','customer'=>'Mekong Agri Exports',   'issue_date'=>'2025-05-22','due_date'=>'2025-06-22','currency'=>'VND','total'=>18000000, 'tax'=>10,'final'=>19800000,'status'=>'ISSUED', 'paid_date'=>null,'ref'=>null],
        ['id'=>'INV006','order_id'=>'ORD008','customer'=>'Pacific Pharma Group',  'issue_date'=>'2025-05-25','due_date'=>'2025-06-25','currency'=>'USD','total'=>8500,     'tax'=>10,'final'=>9350,    'status'=>'ISSUED', 'paid_date'=>null,'ref'=>null],
        ['id'=>'INV007','order_id'=>'ORD009','customer'=>'FreshMart Distribution','issue_date'=>'2025-05-25','due_date'=>'2025-06-25','currency'=>'VND','total'=>4200000,  'tax'=>10,'final'=>4620000, 'status'=>'PAID',   'paid_date'=>'2025-05-26','ref'=>'TXN-2025-0526-001'],
        ['id'=>'INV008','order_id'=>'ORD001','customer'=>'Saigon Textile Corp.',  'issue_date'=>'2025-04-10','due_date'=>'2025-05-10','currency'=>'VND','total'=>35000000, 'tax'=>10,'final'=>38500000,'status'=>'OVERDUE','paid_date'=>null,'ref'=>null],
        ['id'=>'INV009','order_id'=>'ORD011','customer'=>'Saigon Textile Corp.',  'issue_date'=>'2025-05-28','due_date'=>'2025-06-28','currency'=>'VND','total'=>21000000, 'tax'=>10,'final'=>23100000,'status'=>'DRAFT',  'paid_date'=>null,'ref'=>null],
    ];
}

// ─── Invoice Lines ─────────────────────────────────────────────────────────────
function get_invoice_lines(string $invoice_id): array {
    $lines = [
        'INV001'=>[
            ['id'=>'LN001','billing'=>'Base Freight Rate','method'=>'Per KG',  'qty'=>3200,'unit_price'=>9000,  'total'=>28800000],
            ['id'=>'LN002','billing'=>'Fuel Surcharge',   'method'=>'Flat',    'qty'=>1,   'unit_price'=>8000000,'total'=>8000000],
            ['id'=>'LN003','billing'=>'Handling Fee',     'method'=>'Per Pallet','qty'=>12, 'unit_price'=>150000,'total'=>1800000],
            ['id'=>'LN004','billing'=>'Toll Road Levy',   'method'=>'Flat',    'qty'=>1,   'unit_price'=>3900000,'total'=>3900000],
        ],
        'INV002'=>[
            ['id'=>'LN005','billing'=>'Base Freight Rate','method'=>'Per KG',  'qty'=>1850,'unit_price'=>8500,  'total'=>15725000],
            ['id'=>'LN006','billing'=>'Fuel Surcharge',   'method'=>'Flat',    'qty'=>1,   'unit_price'=>8000000,'total'=>8000000],
            ['id'=>'LN007','billing'=>'Express Surcharge','method'=>'Flat',    'qty'=>1,   'unit_price'=>4275000,'total'=>4275000],
        ],
        'INV004'=>[
            ['id'=>'LN008','billing'=>'Air Freight Rate', 'method'=>'Per KG',  'qty'=>220, 'unit_price'=>12,    'total'=>2640],
            ['id'=>'LN009','billing'=>'Air Handling',     'method'=>'Flat',    'qty'=>1,   'unit_price'=>560,   'total'=>560],
        ],
    ];
    return $lines[$invoice_id] ?? [];
}

// ─── Billing Structures ───────────────────────────────────────────────────────
function get_billing_structures(): array {
    return [
        ['id'=>'BIL001','charge_type'=>'Base Freight Rate',   'method'=>'Per KG',   'rate'=>9000,    'currency'=>'VND','active'=>true],
        ['id'=>'BIL002','charge_type'=>'Fuel Surcharge',      'method'=>'Flat',     'rate'=>8000000, 'currency'=>'VND','active'=>true],
        ['id'=>'BIL003','charge_type'=>'Handling Fee',        'method'=>'Per Pallet','rate'=>150000, 'currency'=>'VND','active'=>true],
        ['id'=>'BIL004','charge_type'=>'Toll Road Levy',      'method'=>'Flat',     'rate'=>3900000, 'currency'=>'VND','active'=>true],
        ['id'=>'BIL005','charge_type'=>'Express Surcharge',   'method'=>'Flat',     'rate'=>4275000, 'currency'=>'VND','active'=>true],
        ['id'=>'BIL006','charge_type'=>'Cold-chain Premium',  'method'=>'Per KG',   'rate'=>15000,   'currency'=>'VND','active'=>true],
        ['id'=>'BIL007','charge_type'=>'Air Freight Rate',    'method'=>'Per KG',   'rate'=>12,      'currency'=>'USD','active'=>true],
        ['id'=>'BIL008','charge_type'=>'Air Handling',        'method'=>'Flat',     'rate'=>560,     'currency'=>'USD','active'=>true],
        ['id'=>'BIL009','charge_type'=>'CBM Rate (Sea)',      'method'=>'Per CBM',  'rate'=>85,      'currency'=>'USD','active'=>false],
        ['id'=>'BIL010','charge_type'=>'Insurance Premium',   'method'=>'% of Value','rate'=>0.5,   'currency'=>'VND','active'=>true],
    ];
}

// ─── Payment Transactions ─────────────────────────────────────────────────────
function get_payments(): array {
    return [
        ['id'=>'PAY001','invoice_id'=>'INV001','invoice_customer'=>'Saigon Textile Corp.',  'amount'=>46750000,'currency'=>'VND','date'=>'2025-05-28','ref'=>'TXN-2025-0528-001','method'=>'Bank Transfer'],
        ['id'=>'PAY002','invoice_id'=>'INV003','invoice_customer'=>'FreshMart Distribution','amount'=>7480000, 'currency'=>'VND','date'=>'2025-05-20','ref'=>'TXN-2025-0520-001','method'=>'Bank Transfer'],
        ['id'=>'PAY003','invoice_id'=>'INV007','invoice_customer'=>'FreshMart Distribution','amount'=>4620000, 'currency'=>'VND','date'=>'2025-05-26','ref'=>'TXN-2025-0526-001','method'=>'Cash'],
    ];
}

// ─── System Audit Logs ────────────────────────────────────────────────────────
function get_audit_logs(): array {
    return [
        ['id'=>'AUD001','account'=>'Alex Administrator','role'=>'admin','table'=>'Account',     'action'=>'CREATE','record_id'=>'ACC005','time'=>'2025-05-20 09:15:00','desc'=>'Created new Operations Staff account: Kevin Tran'],
        ['id'=>'AUD002','account'=>'Alex Administrator','role'=>'admin','table'=>'Account',     'action'=>'UPDATE','record_id'=>'ACC006','time'=>'2025-05-21 10:30:00','desc'=>'Deactivated account: Sandra Lee (manager2)'],
        ['id'=>'AUD003','account'=>'Olivia Nguyen',     'role'=>'ops', 'table'=>'Order',        'action'=>'CREATE','record_id'=>'ORD011','time'=>'2025-05-27 08:45:00','desc'=>'Created Order ORD011 for Saigon Textile Corp.'],
        ['id'=>'AUD004','account'=>'James Pham',        'role'=>'acc', 'table'=>'Invoice',      'action'=>'UPDATE','record_id'=>'INV001','time'=>'2025-05-28 14:00:00','desc'=>'Updated INV001 status: ISSUED → PAID'],
        ['id'=>'AUD005','account'=>'Alex Administrator','role'=>'admin','table'=>'Role',         'action'=>'UPDATE','record_id'=>'ROLE002','time'=>'2025-05-22 11:00:00','desc'=>'Updated Manager role: added Report Export permission'],
        ['id'=>'AUD006','account'=>'Kevin Tran',        'role'=>'ops', 'table'=>'Shipment',     'action'=>'CREATE','record_id'=>'SHP006','time'=>'2025-05-24 13:00:00','desc'=>'Created Shipment SHP006 and assigned air cargo slot'],
        ['id'=>'AUD007','account'=>'James Pham',        'role'=>'acc', 'table'=>'Invoice',      'action'=>'CREATE','record_id'=>'INV009','time'=>'2025-05-28 15:30:00','desc'=>'Created Draft Invoice INV009 for ORD011'],
        ['id'=>'AUD008','account'=>'Alex Administrator','role'=>'admin','table'=>'Account',     'action'=>'RESET_PASSWORD','record_id'=>'ACC003','time'=>'2025-05-29 09:00:00','desc'=>'Password reset link generated for: James Pham'],
        ['id'=>'AUD009','account'=>'Olivia Nguyen',     'role'=>'ops', 'table'=>'Operational_Exception','action'=>'UPDATE','record_id'=>'EXC001','time'=>'2025-05-11 10:00:00','desc'=>'Exception EXC001 marked as RESOLVED'],
        ['id'=>'AUD010','account'=>'Maria Chen',        'role'=>'mgr', 'table'=>'Operational_Exception','action'=>'UPDATE','record_id'=>'EXC003','time'=>'2025-05-20 13:00:00','desc'=>'Exception EXC003 status set to IN_REVIEW pending route approval'],
    ];
}

// ─── Notifications ────────────────────────────────────────────────────────────
function get_notifications(string $account_id = ''): array {
    $all = [
        ['id'=>'NOT001','account_id'=>'ACC004','title'=>'New Shipment Assigned',         'msg'=>'Shipment SHP006 has been assigned to SwiftAir Cargo VN for ORD008.',   'is_read'=>false,'created_at'=>'2025-05-24 13:00'],
        ['id'=>'NOT002','account_id'=>'ACC004','title'=>'Delivery Exception Reported',   'msg'=>'Exception EXC004: River barge breakdown on SHP005. Action required.',    'is_read'=>false,'created_at'=>'2025-05-21 13:30'],
        ['id'=>'NOT003','account_id'=>'ACC004','title'=>'Order ORD011 Confirmed',        'msg'=>'Customer Saigon Textile Corp. confirmed Order ORD011.',                  'is_read'=>true, 'created_at'=>'2025-05-27 09:00'],
        ['id'=>'NOT004','account_id'=>'ACC002','title'=>'Delivery Delay Alert',          'msg'=>'Shipment SHP001 arrived 2h 15m late at Hanoi North Depot.',              'is_read'=>false,'created_at'=>'2025-05-12 22:30'],
        ['id'=>'NOT005','account_id'=>'ACC002','title'=>'Exception Requires Approval',   'msg'=>'Exception EXC003 (Route Deviation) awaits Manager review and approval.', 'is_read'=>true, 'created_at'=>'2025-05-20 11:15'],
        ['id'=>'NOT006','account_id'=>'ACC003','title'=>'Invoice INV008 Overdue',        'msg'=>'Invoice INV008 for Saigon Textile Corp. is now 19 days overdue.',        'is_read'=>false,'created_at'=>'2025-05-29 08:00'],
        ['id'=>'NOT007','account_id'=>'ACC003','title'=>'Payment Received – INV001',     'msg'=>'Payment of 46,750,000 VND received for Invoice INV001.',                 'is_read'=>true, 'created_at'=>'2025-05-28 14:05'],
        ['id'=>'NOT008','account_id'=>'ACC005','title'=>'Shipment SHP004 Update',        'msg'=>'Route deviation logged on SHP004. ETA revised to May 21, 03:00.',        'is_read'=>false,'created_at'=>'2025-05-20 11:00'],
    ];
    if ($account_id) {
        return array_filter($all, fn($n) => $n['account_id'] === $account_id);
    }
    return $all;
}

// ─── System Roles ─────────────────────────────────────────────────────────────
function get_roles(): array {
    return [
        ['id'=>'ROLE001','name'=>'Admin',           'user_count'=>1,'permissions'=>['view'=>true,'create'=>true,'edit'=>true,'delete'=>true],  'desc'=>'Full system access including user management and audit logs'],
        ['id'=>'ROLE002','name'=>'Manager',         'user_count'=>1,'permissions'=>['view'=>true,'create'=>false,'edit'=>true,'delete'=>false],'desc'=>'KPI dashboards, reports, exceptions, and request approvals'],
        ['id'=>'ROLE003','name'=>'Accountant',      'user_count'=>1,'permissions'=>['view'=>true,'create'=>true,'edit'=>true,'delete'=>false], 'desc'=>'Invoice creation, billing configuration, payment tracking'],
        ['id'=>'ROLE004','name'=>'Operation Staff', 'user_count'=>2,'permissions'=>['view'=>true,'create'=>true,'edit'=>true,'delete'=>false], 'desc'=>'Shipment management, asset assignment, route planning, tracking'],
    ];
}

// ─── KPI Summary (Manager Dashboard) ─────────────────────────────────────────
function get_kpi_summary(): array {
    return [
        'total_orders'      => 12,
        'delivered'         => 4,
        'in_transit'        => 3,
        'pending'           => 3,
        'cancelled'         => 1,
        'on_time_rate'      => 78.5,
        'total_shipments'   => 7,
        'active_carriers'   => 4,
        'total_routes'      => 8,
        'exceptions_open'   => 2,
        'revenue_month_vnd' => 132350000,
        'revenue_month_usd' => 11670,
        'avg_delivery_days' => 2.4,
        'fleet_utilization' => 71.4,
    ];
}

// ─── Monthly Revenue (chart data) ─────────────────────────────────────────────
function get_monthly_revenue(): array {
    return [
        'labels' => ['Dec','Jan','Feb','Mar','Apr','May'],
        'vnd'    => [88000000, 102000000, 95000000, 118000000, 125000000, 132350000],
        'usd'    => [4200,      5100,       4800,      6200,      9800,      11670],
    ];
}

// ─── Delivery Performance (chart data) ────────────────────────────────────────
function get_delivery_performance(): array {
    return [
        'labels'      => ['Dec','Jan','Feb','Mar','Apr','May'],
        'on_time'     => [82, 79, 85, 80, 76, 78.5],
        'delayed'     => [18, 21, 15, 20, 24, 21.5],
    ];
}

// ─── Cost by Route (chart data) ───────────────────────────────────────────────
function get_cost_by_route(): array {
    return [
        'labels' => ['HCMC→HAN','HCMC→DNG','HCMC→CT','HAN→DNG','Air HAN→SGN','Coastal'],
        'costs'  => [54000000, 29400000, 7650000, 25200000, 15000000, 4960000],
    ];
}

// ─── Proof of Delivery ────────────────────────────────────────────────────────
function get_pods(): array {
    return [
        ['id'=>'POD001','shipment_id'=>'SHP001','order_id'=>'ORD001','recipient'=>'Nguyen Van An','signed_at'=>'2025-05-12 22:15','location'=>'Hanoi North Depot','doc_type'=>'Digital Signature','verified'=>true, 'notes'=>'All 12 pallets received in good condition'],
        ['id'=>'POD002','shipment_id'=>'SHP003','order_id'=>'ORD003','recipient'=>'Tran Thi Binh','signed_at'=>'2025-05-18 09:45','location'=>'Can Tho City Market','doc_type'=>'Photo + Signature','verified'=>true, 'notes'=>'Cold-chain maintained; temp log attached'],
        ['id'=>'POD003','shipment_id'=>'SHP007','order_id'=>'ORD009','recipient'=>'Le Van Cuong','signed_at'=>'2025-05-25 08:45','location'=>'Big C Thu Duc','doc_type'=>'Digital Signature','verified'=>true, 'notes'=>'2 crates returned (damaged in transit)'],
    ];
}

// ─── Delivery Schedules ───────────────────────────────────────────────────────
function get_schedules(): array {
    return [
        ['id'=>'SCH001','shipment_id'=>'SHP001','order_id'=>'ORD001','customer'=>'Saigon Textile Corp.', 'route'=>'HCMC → Hanoi Express','planned_dep'=>'2025-05-10 08:00','actual_dep'=>'2025-05-10 08:30','planned_arr'=>'2025-05-12 20:00','actual_arr'=>'2025-05-12 22:15','variance'=>135, 'status'=>'COMPLETED'],
        ['id'=>'SCH002','shipment_id'=>'SHP002','order_id'=>'ORD002','customer'=>'Hanoi Electronics Ltd.','route'=>'HCMC → Hanoi Express','planned_dep'=>'2025-05-15 09:00','actual_dep'=>'2025-05-15 09:00','planned_arr'=>'2025-05-17 21:00','actual_arr'=>null,'variance'=>null,'status'=>'IN_PROGRESS'],
        ['id'=>'SCH003','shipment_id'=>'SHP003','order_id'=>'ORD003','customer'=>'FreshMart Distribution','route'=>'HCMC → Can Tho Local','planned_dep'=>'2025-05-18 06:00','actual_dep'=>'2025-05-18 06:15','planned_arr'=>'2025-05-18 09:30','actual_arr'=>'2025-05-18 09:45','variance'=>15,  'status'=>'COMPLETED'],
        ['id'=>'SCH004','shipment_id'=>'SHP004','order_id'=>'ORD004','customer'=>'Pacific Pharma Group',  'route'=>'HCMC → Da Nang Standard','planned_dep'=>'2025-05-20 07:00','actual_dep'=>'2025-05-20 07:30','planned_arr'=>'2025-05-21 01:00','actual_arr'=>null,'variance'=>null,'status'=>'IN_PROGRESS'],
        ['id'=>'SCH005','shipment_id'=>'SHP005','order_id'=>'ORD005','customer'=>'Mekong Agri Exports',   'route'=>'HCMC → Can Tho Local','planned_dep'=>'2025-05-21 10:00','actual_dep'=>null,'planned_arr'=>'2025-05-21 14:00','actual_arr'=>null,'variance'=>null,'status'=>'SCHEDULED'],
        ['id'=>'SCH006','shipment_id'=>'SHP006','order_id'=>'ORD008','customer'=>'Pacific Pharma Group',  'route'=>'Air Express HAN→SGN','planned_dep'=>'2025-05-24 14:00','actual_dep'=>'2025-05-24 14:00','planned_arr'=>'2025-05-24 16:00','actual_arr'=>null,'variance'=>null,'status'=>'IN_PROGRESS'],
    ];
}

// ─── Carrier Costs (for reconciliation) ───────────────────────────────────────
function get_carrier_costs(): array {
    return [
        ['shipment_id'=>'SHP001','carrier'=>'VietSpeed Logistics', 'route'=>'HCMC → Hanoi Express',    'mode'=>'Road','status'=>'DELIVERED','base_cost'=>8500000, 'surcharges'=>500000,'total_payable'=>9000000, 'reconciled'=>true],
        ['shipment_id'=>'SHP002','carrier'=>'VietSpeed Logistics', 'route'=>'HCMC → Hanoi Express',    'mode'=>'Road','status'=>'IN_TRANSIT','base_cost'=>8500000,'surcharges'=>500000,'total_payable'=>9000000, 'reconciled'=>false],
        ['shipment_id'=>'SHP003','carrier'=>'MekongShip Transport','route'=>'HCMC → Can Tho Local',    'mode'=>'Road','status'=>'DELIVERED','base_cost'=>850000,  'surcharges'=>80000,'total_payable'=>930000,  'reconciled'=>true],
        ['shipment_id'=>'SHP004','carrier'=>'VietSpeed Logistics', 'route'=>'HCMC → Da Nang Standard', 'mode'=>'Road','status'=>'IN_TRANSIT','base_cost'=>4200000,'surcharges'=>300000,'total_payable'=>4500000, 'reconciled'=>false],
        ['shipment_id'=>'SHP006','carrier'=>'SwiftAir Cargo VN',  'route'=>'Air Express HAN → SGN',   'mode'=>'Air', 'status'=>'IN_TRANSIT','base_cost'=>15000000,'surcharges'=>1200000,'total_payable'=>16200000,'reconciled'=>false],
        ['shipment_id'=>'SHP007','carrier'=>'MekongShip Transport','route'=>'HCMC → Can Tho Local',    'mode'=>'Road','status'=>'DELIVERED','base_cost'=>850000,  'surcharges'=>80000,'total_payable'=>930000,  'reconciled'=>true],
    ];
}

// ─── Manager Requests ─────────────────────────────────────────────────────────
function get_manager_requests(): array {
    return [
        ['id'=>'REQ001','submitted_by'=>'Olivia Nguyen','role'=>'ops','type'=>'Schedule Adjustment','desc'=>'Request to push SHP005 departure from 10:00 to 14:00 due to barge engine fault (see EXC004).','status'=>'PENDING','created_at'=>'2025-05-21 14:00','priority'=>'High'],
        ['id'=>'REQ002','submitted_by'=>'Kevin Tran',   'role'=>'ops','type'=>'Route Deviation',    'desc'=>'QL1 blocked near Quang Ngai; requesting approval to reroute SHP004 via CT04 (adds ~40km).','status'=>'APPROVED','created_at'=>'2025-05-20 11:00','priority'=>'High'],
        ['id'=>'REQ003','submitted_by'=>'Kevin Tran',   'role'=>'ops','type'=>'Special Delivery',   'desc'=>'Customer Pacific Pharma requests Saturday delivery for ORD008 (premium fee applicable).','status'=>'APPROVED','created_at'=>'2025-05-23 09:00','priority'=>'Medium'],
        ['id'=>'REQ004','submitted_by'=>'Olivia Nguyen','role'=>'ops','type'=>'Asset Replacement',  'desc'=>'AST004 barge engine failure; requesting replacement with AST001 for ORD005 delivery.','status'=>'PENDING','created_at'=>'2025-05-21 15:00','priority'=>'High'],
        ['id'=>'REQ005','submitted_by'=>'Kevin Tran',   'role'=>'ops','type'=>'Schedule Adjustment','desc'=>'Customer FreshMart requests next-day delivery for future order. Evaluating fleet availability.','status'=>'REJECTED','created_at'=>'2025-05-19 10:00','priority'=>'Low'],
    ];
}
