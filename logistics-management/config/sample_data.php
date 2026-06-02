<?php
/**
 * sample_data.php — Data layer kết nối thật với tms_g7 (MariaDB 10.4.32)
 * Tất cả functions truy vấn trực tiếp từ database.
 * Password đăng nhập demo được giữ riêng (DB dùng bcrypt trong production).
 */
require_once __DIR__ . '/db.php';

// ─── Helper nội bộ ────────────────────────────────────────────────────────────
function _role_key(string $roleName): string {
    return match(strtolower(trim($roleName))) {
        'admin'          => 'admin',
        'manager'        => 'manager',
        'accountant'     => 'accountant',
        'operation staff'=> 'operations',
        default          => 'operations'
    };
}

// Demo passwords (DB lưu bcrypt — dùng bảng này cho môi trường demo)
function _demo_passwords(): array {
    return [
        'admin_dang'  => 'admin123',
        'mgr_maianh'  => 'manager123',
        'acc_mchen'   => 'accountant123',
        'ops_sarah'   => 'ops123',
        'acc_emma'    => 'accountant123',
        // Tất cả tài khoản còn lại
        'default'     => 'demo123',
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// ACCOUNTS
// ═══════════════════════════════════════════════════════════════════════════════
function get_demo_accounts(): array {
    $db  = get_db();
    $pw  = _demo_passwords();

    $sql = "SELECT a.AccountID, a.EmployeeID, a.RoleID, a.Username, a.Status,
                   e.FullName, e.ContactEmail AS Email, e.Phone,
                   r.RoleName
            FROM account a
            JOIN employee e ON e.EmployeeID = a.EmployeeID
            JOIN role     r ON r.RoleID     = a.RoleID
            ORDER BY a.AccountID";

    $result = $db->query($sql);
    $accounts = [];

    while ($row = $result->fetch_assoc()) {
        // Tạo avatar từ 2 chữ đầu tên
        $parts  = explode(' ', $row['FullName']);
        $avatar = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));

        $accounts[] = [
            'id'          => (int)$row['AccountID'],
            'employee_id' => (int)$row['EmployeeID'],
            'name'        => $row['FullName'],
            'username'    => $row['Username'],
            'email'       => $row['Email'],
            'phone'       => $row['Phone'],
            'role'        => _role_key($row['RoleName']),
            'role_label'  => $row['RoleName'],
            'role_id'     => (int)$row['RoleID'],
            'password'    => $pw[$row['Username']] ?? $pw['default'],
            'status'      => strtolower($row['Status']),
            'avatar'      => $avatar,
        ];
    }
    return $accounts;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ROLES
// ═══════════════════════════════════════════════════════════════════════════════
function get_roles(): array {
    $db = get_db();
    $descriptions = [
        1 => 'Full system access. Manages user accounts, roles, permissions, audit logs, and system configuration.',
        2 => 'Oversees operations strategy, approves exceptions and requests, reviews KPIs, cost analysis, and reports.',
        3 => 'Manages invoices (AR/AP), payment transactions, billing structures, carrier costs, and financial reports.',
        4 => 'Handles daily logistics: shipment management, asset assignment, tracking, inventory, exceptions, and POD.',
    ];
    $permissions = [
        1 => ['view'=>true,'create'=>true,'edit'=>true,'delete'=>true],
        2 => ['view'=>true,'create'=>true,'edit'=>true,'delete'=>false],
        3 => ['view'=>true,'create'=>true,'edit'=>true,'delete'=>false],
        4 => ['view'=>true,'create'=>true,'edit'=>true,'delete'=>false],
    ];

    $sql = "SELECT r.RoleID, r.RoleName, COUNT(a.AccountID) AS user_count
            FROM role r
            LEFT JOIN account a ON a.RoleID = r.RoleID
            GROUP BY r.RoleID, r.RoleName
            ORDER BY r.RoleID";

    $result = $db->query($sql);
    $roles  = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['RoleID'];
        $roles[] = [
            'id'          => $id,
            'name'        => $row['RoleName'],
            'key'         => _role_key($row['RoleName']),
            'user_count'  => (int)$row['user_count'],
            'description' => $descriptions[$id] ?? '',
            'permissions' => $permissions[$id]  ?? ['view'=>true,'create'=>false,'edit'=>false,'delete'=>false],
        ];
    }
    return $roles;
}

// ═══════════════════════════════════════════════════════════════════════════════
// EMPLOYEES
// ═══════════════════════════════════════════════════════════════════════════════
function get_employees(): array {
    $db = get_db();
    $sql = "SELECT e.EmployeeID, e.RoleID, e.WarehouseID, e.FullName,
                   e.DoB, e.ContactEmail, e.Phone, r.RoleName
            FROM employee e
            JOIN role r ON r.RoleID = e.RoleID
            ORDER BY e.EmployeeID";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $parts  = explode(' ', $row['FullName']);
        $avatar = strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
        $out[] = [
            'id'          => (int)$row['EmployeeID'],
            'role_id'     => (int)$row['RoleID'],
            'role_label'  => $row['RoleName'],
            'warehouse_id'=> (int)$row['WarehouseID'],
            'name'        => $row['FullName'],
            'dob'         => $row['DoB'],
            'email'       => $row['ContactEmail'],
            'phone'       => $row['Phone'],
            'avatar'      => $avatar,
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// AUDIT LOGS
// ═══════════════════════════════════════════════════════════════════════════════
function get_audit_logs(): array {
    $db = get_db();
    $sql = "SELECT sal.LogID, sal.AccountID, a.Username,
                   r.RoleName, sal.TableName, sal.ActionType,
                   sal.RecordID, sal.ActionTime, sal.Description
            FROM system_audit_log sal
            JOIN account a ON a.AccountID = sal.AccountID
            JOIN role    r ON r.RoleID    = a.RoleID
            ORDER BY sal.LogID DESC";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id'          => (int)$row['LogID'],
            'account_id'  => (int)$row['AccountID'],
            'account'     => $row['Username'],
            'role'        => $row['RoleName'],
            'action'      => $row['ActionType'],
            'table'       => $row['TableName'],
            'record_id'   => (int)$row['RecordID'],
            'time'        => $row['ActionTime'],
            'description' => $row['Description'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// CUSTOMERS
// ═══════════════════════════════════════════════════════════════════════════════
function get_customers(): array {
    $db = get_db();
    $sql = "SELECT bp.PartyID, bp.PartyName, bp.Address, bp.ContactEmail, bp.Phone,
                   cu.CustomerType, cu.TaxCode, cu.Industry
            FROM business_party bp
            JOIN customer cu ON cu.PartyID = bp.PartyID
            ORDER BY bp.PartyID";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id'       => (int)$row['PartyID'],
            'name'     => $row['PartyName'],
            'address'  => $row['Address'],
            'email'    => $row['ContactEmail'],
            'phone'    => $row['Phone'],
            'type'     => $row['CustomerType'],
            'tax_code' => $row['TaxCode'],
            'industry' => $row['Industry'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// CARRIERS
// ═══════════════════════════════════════════════════════════════════════════════
function get_carriers(): array {
    $db = get_db();
    $sql = "SELECT bp.PartyID, bp.PartyName, bp.Address, bp.ContactEmail, bp.Phone,
                   c.Capabilities, c.PFM_Score, c.Note, c.ServiceArea, c.Status
            FROM business_party bp
            JOIN carrier c ON c.PartyID = bp.PartyID
            ORDER BY bp.PartyID";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        // Phân loại mode vận chuyển từ Capabilities
        $cap  = $row['Capabilities'] ?? '';
        $mode = str_contains($cap,'Ocean')?'Ocean':(str_contains($cap,'Air')?'Air':(str_contains($cap,'Rail')?'Rail':'Road'));
        $out[] = [
            'id'           => (int)$row['PartyID'],
            'name'         => $row['PartyName'],
            'address'      => $row['Address'],
            'email'        => $row['ContactEmail'],
            'phone'        => $row['Phone'],
            'capabilities' => $cap,
            'mode'         => $mode,
            'pfm'          => (float)$row['PFM_Score'],
            'note'         => $row['Note'],
            'area'         => $row['ServiceArea'],
            'status'       => $row['Status'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ORDERS
// ═══════════════════════════════════════════════════════════════════════════════
function get_orders(): array {
    $db = get_db();
    $sql = "SELECT oi.OrderID, oi.AccountID, oi.CustomerID, oi.PickupAddress,
                   oi.OrderDate, oi.ExpectedDeliveryDate, oi.ShippingStatus,
                   bp.PartyName AS customer_name, cu.CustomerType,
                   a.Username   AS operator
            FROM order_info oi
            JOIN business_party bp ON bp.PartyID  = oi.CustomerID
            JOIN customer       cu ON cu.PartyID  = oi.CustomerID
            JOIN account         a ON a.AccountID = oi.AccountID
            ORDER BY oi.OrderID DESC";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id'               => (int)$row['OrderID'],
            'account_id'       => (int)$row['AccountID'],
            'operator'         => $row['operator'],
            'customer_id'      => (int)$row['CustomerID'],
            'customer'         => $row['customer_name'],
            'customer_type'    => $row['CustomerType'],
            'pickup_address'   => $row['PickupAddress'],
            'order_date'       => $row['OrderDate'],
            'expected_delivery'=> $row['ExpectedDeliveryDate'],
            'status'           => $row['ShippingStatus'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// SHIPMENTS
// ═══════════════════════════════════════════════════════════════════════════════
function get_shipments(): array {
    $db = get_db();
    $sql = "SELECT s.ShipmentID, s.RouteID, s.AssetID, s.Status,
                   s.PlannedDeparture, s.DeliveryDeadline,
                   s.EstimatedArrival, s.ActualDeparture, s.ActualArrival,
                   r.RouteName, r.StartLocation, r.EndLocation, r.TransportMode,
                   ta.CarrierID,
                   bp.PartyName AS carrier_name
            FROM shipment s
            JOIN route           r  ON r.RouteID   = s.RouteID
            JOIN transport_asset ta ON ta.AssetID  = s.AssetID
            JOIN business_party  bp ON bp.PartyID  = ta.CarrierID
            ORDER BY s.ShipmentID";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id'               => (int)$row['ShipmentID'],
            'route_id'         => (int)$row['RouteID'],
            'route'            => $row['RouteName'],
            'route_start'      => $row['StartLocation'],
            'route_end'        => $row['EndLocation'],
            'mode'             => $row['TransportMode'],
            'asset_id'         => (int)$row['AssetID'],
            'carrier_id'       => (int)$row['CarrierID'],
            'carrier'          => $row['carrier_name'],
            'status'           => $row['Status'],
            'planned_departure'=> $row['PlannedDeparture'],
            'delivery_deadline'=> $row['DeliveryDeadline'],
            'estimated_arrival'=> $row['EstimatedArrival'],
            'actual_departure' => $row['ActualDeparture'],
            'actual_arrival'   => $row['ActualArrival'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// TRANSPORT ASSETS
// ═══════════════════════════════════════════════════════════════════════════════
function get_transport_assets(): array {
    $db = get_db();
    $sql = "SELECT ta.AssetID, ta.CarrierID, ta.AssetCategory,
                   ta.VehicleModel, ta.MaxWeight, ta.MaxVolume,
                   bp.PartyName AS carrier_name,
                   c.Capabilities, c.Status AS carrier_status
            FROM transport_asset ta
            JOIN business_party bp ON bp.PartyID = ta.CarrierID
            JOIN carrier        c  ON c.PartyID  = ta.CarrierID
            ORDER BY ta.AssetID";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $cap  = $row['Capabilities'] ?? '';
        $mode = str_contains($cap,'Ocean')?'Ocean':(str_contains($cap,'Air')?'Air':(str_contains($cap,'Rail')?'Rail':'Road'));
        $out[] = [
            'id'         => (int)$row['AssetID'],
            'carrier_id' => (int)$row['CarrierID'],
            'carrier'    => $row['carrier_name'],
            'mode'       => $mode,
            'category'   => $row['AssetCategory'],
            'model'      => $row['VehicleModel'],
            'max_weight' => (float)$row['MaxWeight'],
            'max_volume' => (float)$row['MaxVolume'],
            'status'     => 'Available',
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ROUTES
// ═══════════════════════════════════════════════════════════════════════════════
function get_routes(): array {
    $db = get_db();
    $sql = "SELECT RouteID, RouteName, StartLocation, EndLocation,
                   TransportMode, EstimatedDistance, EstimatedDuration, DurationUnit
            FROM route
            ORDER BY RouteID";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id'       => (int)$row['RouteID'],
            'name'     => $row['RouteName'],
            'start'    => $row['StartLocation'],
            'end'      => $row['EndLocation'],
            'mode'     => $row['TransportMode'],
            'dist'     => (float)$row['EstimatedDistance'],
            'dur'      => (float)$row['EstimatedDuration'],
            'dur_unit' => $row['DurationUnit'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// SCHEDULES (tính từ shipment — so sánh planned vs actual)
// ═══════════════════════════════════════════════════════════════════════════════
function get_schedules(): array {
    $shipments = get_shipments();
    return array_map(function($s) {
        $variance = null;
        $status   = 'Scheduled';
        if ($s['status'] === 'Delivered' && $s['actual_arrival'] && $s['estimated_arrival']) {
            $variance = (int)((strtotime($s['actual_arrival']) - strtotime($s['estimated_arrival'])) / 60);
            $status = 'Completed';
        } elseif ($s['status'] === 'In Transit') {
            $status = 'In Progress';
        }
        return [
            'id'               => $s['id'],
            'shipment_id'      => $s['id'],
            'route'            => $s['route'],
            'mode'             => $s['mode'],
            'carrier'          => $s['carrier'],
            'planned_dep'      => $s['planned_departure'],
            'actual_dep'       => $s['actual_departure'],
            'planned_arr'      => $s['estimated_arrival'],
            'actual_arr'       => $s['actual_arrival'],
            'delivery_deadline'=> $s['delivery_deadline'],
            'variance_min'     => $variance,
            'status'           => $status,
        ];
    }, $shipments);
}

// ═══════════════════════════════════════════════════════════════════════════════
// TRACKING LOGS
// ═══════════════════════════════════════════════════════════════════════════════
function get_tracking_logs(int $shipmentId = 0): array {
    $db = get_db();
    $weather_emoji = [
        'Clear'      => '☀️', 'Rain'       => '🌧️', 'Heavy Rain' => '⛈️',
        'Snowstorm'  => '❄️', 'Fog'        => '🌫️', 'Typhoon'   => '🌪️',
        'Sandstorm'  => '🏜️',
    ];
    if ($shipmentId > 0) {
        $stmt = $db->prepare("SELECT tl.LogID, tl.ShipmentID, tl.AccountID, tl.Timestamp,
                   tl.CheckpointLocation, tl.WeatherCondition,
                   tl.TrafficDelayTime, tl.TrafficDelayTimeUnit,
                   a.Username AS operator
            FROM tracking_log tl
            JOIN account a ON a.AccountID = tl.AccountID
            WHERE tl.ShipmentID = ?
            ORDER BY tl.Timestamp ASC");
        $stmt->bind_param('i', $shipmentId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query("SELECT tl.LogID, tl.ShipmentID, tl.AccountID, tl.Timestamp,
                   tl.CheckpointLocation, tl.WeatherCondition,
                   tl.TrafficDelayTime, tl.TrafficDelayTimeUnit,
                   a.Username AS operator
            FROM tracking_log tl
            JOIN account a ON a.AccountID = tl.AccountID
            ORDER BY tl.Timestamp DESC");
    }
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $del  = (int)$row['TrafficDelayTime'];
        $unit = $row['TrafficDelayTimeUnit'];
        $mins = match($unit) { 'Days'=>$del*1440, 'Hours'=>$del*60, default=>$del };
        $out[] = [
            'log_id'        => (int)$row['LogID'],
            'shipment_id'   => (int)$row['ShipmentID'],
            'account_id'    => (int)$row['AccountID'],
            'operator'      => $row['operator'],
            'timestamp'     => $row['Timestamp'],
            'location'      => $row['CheckpointLocation'],
            'weather'       => $row['WeatherCondition'],
            'weather_emoji' => $weather_emoji[$row['WeatherCondition']] ?? '☀️',
            'delay_raw'     => $del,
            'delay_unit'    => $unit,
            'delay_minutes' => $mins,
            'notes'         => 'Logged by ' . $row['operator'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// SKUs
// ═══════════════════════════════════════════════════════════════════════════════
function get_skus(): array {
    $db = get_db();
    $thresholds = [1=>500,2=>500,3=>200,4=>150,5=>50,6=>1000,7=>1000,8=>1000,
                   9=>2000,10=>500,11=>500,12=>300,13=>100,14=>100,15=>1000,
                   16=>500,17=>100,18=>50,19=>500,20=>100];
    $result = $db->query("SELECT SKUID, SKUName, UOM, Quantity FROM sku ORDER BY SKUID");
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $id  = (int)$row['SKUID'];
        $qty = (int)$row['Quantity'];
        $thr = $thresholds[$id] ?? 100;
        $out[] = [
            'id'        => $id,
            'name'      => $row['SKUName'],
            'uom'       => $row['UOM'],
            'quantity'  => $qty,
            'threshold' => $thr,
            'status'    => $qty <= $thr ? 'Low' : 'Adequate',
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// WAREHOUSES (không có bảng trong tms_g7 — dùng WarehouseID từ employee)
// ═══════════════════════════════════════════════════════════════════════════════
function get_warehouses(): array {
    // Dữ liệu kho dựa trên WarehouseID trong bảng employee
    return [
        ['id'=>1,'name'=>'Hanoi HQ Warehouse',      'code'=>'WH-HAN','city'=>'Hanoi, Vietnam',           'capacity'=>10000,'used'=>7500],
        ['id'=>2,'name'=>'Singapore Hub',            'code'=>'WH-SGP','city'=>'Singapore',                'capacity'=>25000,'used'=>18000],
        ['id'=>3,'name'=>'Los Angeles Gateway',      'code'=>'WH-LAX','city'=>'Los Angeles, USA',         'capacity'=>20000,'used'=>14000],
        ['id'=>4,'name'=>'Frankfurt Euro Hub',       'code'=>'WH-FRA','city'=>'Frankfurt, Germany',       'capacity'=>15000,'used'=>9000],
        ['id'=>5,'name'=>'Singapore WH2',            'code'=>'WH-SGP2','city'=>'Singapore',               'capacity'=>12000,'used'=>8500],
        ['id'=>6,'name'=>'Dubai Middle East Hub',    'code'=>'WH-DXB','city'=>'Dubai, UAE',               'capacity'=>12000,'used'=>7000],
        ['id'=>7,'name'=>'Shenzhen China WH',        'code'=>'WH-SZX','city'=>'Shenzhen, China',          'capacity'=>30000,'used'=>22000],
        ['id'=>8,'name'=>'Amsterdam WH',             'code'=>'WH-AMS','city'=>'Amsterdam, Netherlands',   'capacity'=>10000,'used'=>6000],
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// INVOICES
// ═══════════════════════════════════════════════════════════════════════════════
function get_invoices(): array {
    $db = get_db();
    $sql = "SELECT i.InvoiceID, i.OrderID, i.BilledPartyID, i.InvoiceType,
                   i.UserID, i.TotalPreAmount, i.TaxRate, i.FinalAmount,
                   i.IssueDate, i.Note,
                   bp.PartyName AS party_name,
                   a.Username   AS issued_by,
                   COALESCE(SUM(pt.AmountPaid), 0) AS amount_paid
            FROM invoice i
            JOIN business_party       bp ON bp.PartyID  = i.BilledPartyID
            JOIN account               a ON a.AccountID = i.UserID
            LEFT JOIN payment_transaction pt ON pt.InvoiceID = i.InvoiceID
            GROUP BY i.InvoiceID
            ORDER BY i.InvoiceID DESC";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $final    = (float)$row['FinalAmount'];
        $paid     = (float)$row['amount_paid'];
        $due_ts   = strtotime($row['IssueDate']) + 30 * 86400;
        $is_ar    = $row['InvoiceType'] === 'AR_Receivable';
        // Tính status: so sánh tiền đã thanh toán với tổng hoá đơn
        if ($paid >= $final)                              $status = 'Paid';
        elseif ($is_ar && $due_ts < time() && $paid < $final) $status = 'Overdue';
        elseif ($paid > 0)                                $status = 'Partial';
        else                                              $status = 'Issued';

        $out[] = [
            'id'           => 'INV' . str_pad($row['InvoiceID'], 3, '0', STR_PAD_LEFT),
            'invoice_id'   => (int)$row['InvoiceID'],
            'order_id'     => $row['OrderID'] ? 'ORD'.str_pad($row['OrderID'],3,'0',STR_PAD_LEFT) : null,
            'party_id'     => (int)$row['BilledPartyID'],
            'party_name'   => $row['party_name'],
            'type'         => $row['InvoiceType'],
            'type_label'   => $row['InvoiceType'] === 'AR_Receivable' ? 'Receivable (AR)' : 'Payable (AP)',
            'issued_by'    => $row['issued_by'],
            'pre_amount'   => (float)$row['TotalPreAmount'],
            'tax_rate'     => (float)$row['TaxRate'],
            'final_amount' => $final,
            'amount_paid'  => $paid,
            'currency'     => 'USD',
            'issue_date'   => $row['IssueDate'],
            'due_date'     => date('Y-m-d', $due_ts),
            'status'       => $status,
            'note'         => $row['Note'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// INVOICE LINES
// ═══════════════════════════════════════════════════════════════════════════════
function get_invoice_lines(string $invoiceId): array {
    $db     = get_db();
    $invNum = (int)ltrim($invoiceId, 'INV0');
    $stmt   = $db->prepare("SELECT il.LineID, il.InvoiceID, il.BillingID,
                   il.ChargeMethod, il.Quantity, il.UnitPrice, il.LineTotal,
                   bs.ChargeType, bs.Currency
            FROM invoice_line    il
            JOIN billing_structure bs ON bs.BillingID = il.BillingID
            WHERE il.InvoiceID = ?
            ORDER BY il.LineID");
    $stmt->bind_param('i', $invNum);
    $stmt->execute();
    $result = $stmt->get_result();
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'line_id'     => (int)$row['LineID'],
            'invoice_id'  => (int)$row['InvoiceID'],
            'billing_id'  => (int)$row['BillingID'],
            'charge_type' => $row['ChargeType'],
            'method'      => $row['ChargeMethod'],
            'quantity'    => (float)$row['Quantity'],
            'unit_price'  => (float)$row['UnitPrice'],
            'line_total'  => (float)$row['LineTotal'],
            'currency'    => $row['Currency'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// PAYMENTS
// ═══════════════════════════════════════════════════════════════════════════════
function get_payments(): array {
    $db = get_db();
    $sql = "SELECT pt.PaymentID, pt.InvoiceID, pt.AmountPaid,
                   pt.ReferenceCode, pt.PaymentDate,
                   bp.PartyName AS party_name, i.InvoiceType
            FROM payment_transaction pt
            JOIN invoice        i  ON i.InvoiceID  = pt.InvoiceID
            JOIN business_party bp ON bp.PartyID   = i.BilledPartyID
            ORDER BY pt.PaymentDate DESC";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        // Đoán phương thức thanh toán từ reference code
        $ref  = $row['ReferenceCode'];
        $method = 'Bank Transfer';
        if (str_starts_with($ref,'MOMO'))  $method = 'MoMo E-wallet';
        elseif (str_starts_with($ref,'VNPAY')) $method = 'VNPAY';
        elseif (str_starts_with($ref,'SEPA'))  $method = 'SEPA Transfer';
        elseif (str_starts_with($ref,'SWIFT')) $method = 'SWIFT Transfer';
        elseif (str_starts_with($ref,'ACH'))   $method = 'ACH Transfer';
        elseif (str_starts_with($ref,'WIRE'))  $method = 'Wire Transfer';

        $out[] = [
            'id'           => (int)$row['PaymentID'],
            'invoice_id'   => 'INV'.str_pad($row['InvoiceID'],3,'0',STR_PAD_LEFT),
            'invoice_num'  => (int)$row['InvoiceID'],
            'party_name'   => $row['party_name'],
            'type'         => $row['InvoiceType'] === 'AR_Receivable' ? 'AR' : 'AP',
            'amount'       => (float)$row['AmountPaid'],
            'currency'     => 'USD',
            'reference'    => $ref,
            'payment_date' => $row['PaymentDate'],
            'method'       => $method,
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// BILLING STRUCTURES
// ═══════════════════════════════════════════════════════════════════════════════
function get_billing_structures(): array {
    $db     = get_db();
    $result = $db->query("SELECT BillingID, ChargeType, RateValue, Currency, Status
                          FROM billing_structure ORDER BY BillingID");
    $out = [];
    while ($row = $result->fetch_assoc()) {
        // Đoán method từ tên
        $type   = $row['ChargeType'];
        $method = 'Flat Rate';
        if (str_contains($type,'Per KG') || str_contains($type,'Per KG')) $method = 'Per KG';
        elseif (str_contains($type,'Per FEU') || str_contains($type,'Per TEU') || str_contains($type,'Per Container')) $method = 'Per Container';
        elseif (str_contains($type,'Per Pallet')) $method = 'Per Pallet';
        elseif (str_contains($type,'Per Trip')) $method = 'Per Trip';

        $out[] = [
            'id'       => (int)$row['BillingID'],
            'type'     => $row['ChargeType'],
            'rate'     => (float)$row['RateValue'],
            'method'   => $method,
            'currency' => $row['Currency'],
            'status'   => $row['Status'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// CARRIER COSTS (AP_Payable invoices = tiền trả cho carrier)
// ═══════════════════════════════════════════════════════════════════════════════
function get_carrier_costs(): array {
    $db = get_db();
    $sql = "SELECT i.InvoiceID, i.OrderID, i.BilledPartyID, i.TotalPreAmount,
                   i.FinalAmount, i.IssueDate,
                   bp.PartyName AS carrier_name,
                   COALESCE(SUM(pt.AmountPaid),0) AS amount_paid,
                   c.Capabilities
            FROM invoice i
            JOIN business_party       bp ON bp.PartyID  = i.BilledPartyID
            JOIN carrier               c ON c.PartyID   = i.BilledPartyID
            LEFT JOIN payment_transaction pt ON pt.InvoiceID = i.InvoiceID
            WHERE i.InvoiceType = 'AP_Payable'
            GROUP BY i.InvoiceID
            ORDER BY i.InvoiceID";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $cap  = $row['Capabilities'] ?? '';
        $mode = str_contains($cap,'Ocean')?'Ocean':(str_contains($cap,'Air')?'Air':(str_contains($cap,'Rail')?'Rail':'Road'));
        $fin  = (float)$row['FinalAmount'];
        $pre  = (float)$row['TotalPreAmount'];
        $paid = (float)$row['amount_paid'];

        $out[] = [
            'id'            => (int)$row['InvoiceID'],
            'invoice_id'    => 'INV'.str_pad($row['InvoiceID'],3,'0',STR_PAD_LEFT),
            'shipment_id'   => $row['OrderID'] ? 'ORD'.str_pad($row['OrderID'],3,'0',STR_PAD_LEFT) : 'Multi',
            'carrier_id'    => (int)$row['BilledPartyID'],
            'carrier'       => $row['carrier_name'],
            'mode'          => $mode,
            'base_cost'     => $pre,
            'surcharges'    => round($fin - $pre * ($fin > $pre ? 1 : 0), 2),
            'total_payable' => $fin,
            'currency'      => 'USD',
            'invoice_date'  => $row['IssueDate'],
            'reconciled'    => $paid >= $fin ? 'Yes' : 'No',
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// AGING DEBT (AR chưa thanh toán đủ)
// ═══════════════════════════════════════════════════════════════════════════════
function get_aging_debt(): array {
    $invoices = get_invoices();
    return array_values(array_filter($invoices,
        fn($i) => $i['type'] === 'AR_Receivable' && $i['status'] !== 'Paid'
    ));
}

// ═══════════════════════════════════════════════════════════════════════════════
// EXCEPTIONS
// ═══════════════════════════════════════════════════════════════════════════════
function get_exceptions(): array {
    $db = get_db();
    $sql = "SELECT oe.ExceptionID, oe.CarrierID, oe.ShipmentID, oe.AccountID,
                   oe.IssueType, oe.Description, oe.SeverityLevel,
                   oe.ApprovalStatus, oe.CreatedAt,
                   bp.PartyName AS carrier_name,
                   a.Username   AS reporter
            FROM operational_exception oe
            JOIN business_party bp ON bp.PartyID  = oe.CarrierID
            JOIN account        a  ON a.AccountID = oe.AccountID
            ORDER BY oe.ExceptionID DESC";
    $result = $db->query($sql);
    $status_map = ['Approved'=>'RESOLVED','Pending'=>'OPEN','Under Investigation'=>'IN_REVIEW'];
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id'              => (int)$row['ExceptionID'],
            'carrier_id'      => (int)$row['CarrierID'],
            'carrier'         => $row['carrier_name'],
            'shipment_id'     => 'SHP'.str_pad($row['ShipmentID'],3,'0',STR_PAD_LEFT),
            'account_id'      => (int)$row['AccountID'],
            'reporter'        => $row['reporter'],
            'type'            => $row['IssueType'],
            'description'     => $row['Description'],
            'severity'        => $row['SeverityLevel'],
            'status'          => $status_map[$row['ApprovalStatus']] ?? 'OPEN',
            'approval_status' => $row['ApprovalStatus'],
            'created_at'      => $row['CreatedAt'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════════════════════
function get_notifications(string $accountId = ''): array {
    $db = get_db();
    $icons = [
        'System Maintenance'=>'🔧','Carrier Alert'=>'⚠️','Overdue Invoice'=>'🧾',
        'Exception Alert'=>'🚨','Route Changed'=>'🗺️','Low Stock'=>'📦',
        'Security Alert'=>'🛡️','Approval Needed'=>'✅','Payment Received'=>'💳',
        'New Order Assigned'=>'📋','Report Ready'=>'📊','Inventory Count'=>'🏭',
        'Backup Complete'=>'💾','Budget Warning'=>'💰','Tax Rate Update'=>'📝',
        'Customer Complaint'=>'😟','Schedule Update'=>'📅',
        'Equipment Maintenance'=>'🔩','New User'=>'👤','KPI Milestone'=>'🏆',
    ];

    if ($accountId !== '') {
        // Lấy AccountID từ username
        $stmt = $db->prepare("SELECT AccountID FROM account WHERE Username = ?");
        $stmt->bind_param('s', $accountId);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if (!$r) return [];
        $aid  = (int)$r['AccountID'];
        $stmt2 = $db->prepare("SELECT NotifID, AccountID, Title, Message, IsRead, CreatedAt
                                FROM system_notification WHERE AccountID = ?
                                ORDER BY CreatedAt DESC");
        $stmt2->bind_param('i', $aid);
        $stmt2->execute();
        $result = $stmt2->get_result();
    } else {
        $result = $db->query("SELECT NotifID, AccountID, Title, Message, IsRead, CreatedAt
                               FROM system_notification ORDER BY CreatedAt DESC");
    }

    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id'         => (int)$row['NotifID'],
            'account_id' => (int)$row['AccountID'],
            'title'      => $row['Title'],
            'message'    => $row['Message'],
            'is_read'    => $row['IsRead'] === '1',
            'icon'       => $icons[$row['Title']] ?? '🔔',
            'created_at' => $row['CreatedAt'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ANALYTICS / REPORTS
// ═══════════════════════════════════════════════════════════════════════════════
function get_monthly_revenue(): array {
    $db  = get_db();
    $sql = "SELECT DATE_FORMAT(IssueDate,'%b %Y') AS month,
                   YEAR(IssueDate) AS yr, MONTH(IssueDate) AS mo,
                   SUM(FinalAmount) AS revenue,
                   COUNT(*) AS orders
            FROM invoice
            WHERE InvoiceType = 'AR_Receivable'
            GROUP BY yr, mo
            ORDER BY yr ASC, mo ASC";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'month'   => $row['month'],
            'revenue' => (float)$row['revenue'],
            'orders'  => (int)$row['orders'],
        ];
    }
    return $out;
}

function get_delivery_performance(): array {
    $db  = get_db();
    $sql = "SELECT Status, COUNT(*) AS cnt FROM shipment GROUP BY Status";
    $result = $db->query($sql);
    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $counts[$row['Status']] = (int)$row['cnt'];
    }
    return [
        ['label'=>'Delivered',  'count'=>$counts['Delivered']  ?? 0, 'color'=>'#6B8C3E'],
        ['label'=>'In Transit', 'count'=>$counts['In Transit'] ?? 0, 'color'=>'#1a5fa8'],
        ['label'=>'Pending',    'count'=>$counts['Pending']    ?? 0, 'color'=>'#E8B84B'],
    ];
}

function get_cost_by_route(): array {
    $db  = get_db();
    $sql = "SELECT r.RouteID, r.RouteName, r.TransportMode,
                   SUM(i.FinalAmount) AS total_cost
            FROM invoice i
            JOIN shipment_order so ON so.OrderID = i.OrderID
            JOIN shipment        s ON s.ShipmentID = so.ShipmentID
            JOIN route           r ON r.RouteID = s.RouteID
            WHERE i.InvoiceType = 'AP_Payable'
            GROUP BY r.RouteID
            ORDER BY total_cost DESC";
    $result = $db->query($sql);
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id'       => (int)$row['RouteID'],
            'name'     => $row['RouteName'],
            'mode'     => $row['TransportMode'],
            'cost_usd' => (float)$row['total_cost'],
        ];
    }
    return $out;
}

// ═══════════════════════════════════════════════════════════════════════════════
// MANAGER REQUESTS & PODs — Không có bảng trong tms_g7, giữ mock data
// ═══════════════════════════════════════════════════════════════════════════════
function get_manager_requests(): array {
    return [
        ['id'=>1,'submitted_by'=>'ops_sarah','type'=>'Exception Resolution','priority'=>'High',
         'description'=>'Request approval for $500 surcharge claim — Maersk weather delay (SHP#1).','status'=>'Approved','date'=>'2026-06-02'],
        ['id'=>2,'submitted_by'=>'acc_mchen','type'=>'Budget Approval','priority'=>'Medium',
         'description'=>'Q3 carrier contract renewal budget — Kuehne+Nagel $120,000.','status'=>'Pending','date'=>'2026-06-02'],
        ['id'=>3,'submitted_by'=>'ops_ahmed','type'=>'Carrier Change','priority'=>'High',
         'description'=>'Request to replace Ninja Van VN (PFM 2.90) for Vietnam last-mile routes.','status'=>'Approved','date'=>'2026-06-01'],
        ['id'=>4,'submitted_by'=>'ops_wang','type'=>'Route Optimization','priority'=>'Low',
         'description'=>'Proposal to consolidate SGN last-mile delivery routes — reduce cost 15%.','status'=>'Rejected','date'=>'2026-05-28'],
        ['id'=>5,'submitted_by'=>'ops_cuong','type'=>'Asset Maintenance','priority'=>'High',
         'description'=>'COSCO vessel (Asset #5) requires 14-day maintenance window.','status'=>'Pending','date'=>'2026-06-02'],
    ];
}

function get_pods(): array {
    return [
        ['id'=>'POD001','shipment_id'=>'SHP001','order_id'=>'ORD001','customer'=>'Samsung Electronics',
         'recipient'=>'Samsung SC Dept','signed_at'=>'2026-05-18 15:30:00','location'=>'Long Beach Port, CA',
         'doc_type'=>'Electronic','signature_type'=>'Digital','verified'=>true,'notes'=>'All 3 FEU received in good condition.'],
        ['id'=>'POD002','shipment_id'=>'SHP002','order_id'=>'ORD003','customer'=>'Sony Corporation',
         'recipient'=>'Sony Logistics Dept','signed_at'=>'2026-06-01 20:20:00','location'=>'Frankfurt Airport, Germany',
         'doc_type'=>'Paper + Photo','signature_type'=>'Handwritten','verified'=>true,'notes'=>'1,000 KG air freight confirmed.'],
        ['id'=>'POD006','shipment_id'=>'SHP006','order_id'=>'ORD006','customer'=>'Walmart Global',
         'recipient'=>'Walmart Import Mgr','signed_at'=>'2026-05-29 14:15:00','location'=>'Chicago Rail Terminal, IL',
         'doc_type'=>'Electronic','signature_type'=>'Digital','verified'=>true,'notes'=>'3 FEU containers confirmed.'],
        ['id'=>'POD008','shipment_id'=>'SHP008','order_id'=>'ORD008','customer'=>'Minh Tuan Mobile',
         'recipient'=>'Minh Tuan (Owner)','signed_at'=>'2026-06-02 16:55:00','location'=>'43 Tran Quang Khai, HCMC',
         'doc_type'=>'Photo + App','signature_type'=>'Mobile App','verified'=>true,'notes'=>'3 items delivered, signed on app.'],
        ['id'=>'POD011','shipment_id'=>'SHP011','order_id'=>'ORD012','customer'=>'Nike Inc',
         'recipient'=>'Nike Freight Team','signed_at'=>'2026-05-29 08:20:00','location'=>'LAX Cargo Terminal, CA',
         'doc_type'=>'Electronic','signature_type'=>'Digital','verified'=>true,'notes'=>'4,500 KG air shipment. No damage.'],
    ];
}
