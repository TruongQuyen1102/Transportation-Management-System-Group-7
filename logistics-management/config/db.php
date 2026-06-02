<?php
/**
 * db.php — Kết nối MySQL singleton cho tms_g7
 * Không echo gì — chỉ dùng để include
 */
function get_db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli('localhost', 'root', '', 'tms_g7');
        if ($conn->connect_error) {
            die('<div style="font-family:monospace;padding:20px;color:#c0392b;background:#fdf0f0;border:1px solid #e74c3c;border-radius:8px;margin:20px;">
                <strong>⚠️ Database Connection Error:</strong><br>' .
                htmlspecialchars($conn->connect_error) .
                '<br><br><small>Kiểm tra: XAMPP MySQL đang chạy và database <strong>tms_g7</strong> đã được import.</small>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}