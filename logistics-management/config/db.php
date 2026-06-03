<?php
// Bắt buộc phải bọc trong function get_db() như thế này
function get_db() {
    $servername = "localhost"; 
    $username = "root"; 
    $password = ""; 
    $dbname = "tms_g7"; 

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Nếu lỗi thì dừng web và báo lỗi
    if ($conn->connect_error) {
        die("Kết nối Database thất bại: " . $conn->connect_error);
    }
    
    // Set charset để không lỗi font tiếng Việt
    $conn->set_charset("utf8mb4");
    
    // Trả về kết nối (Tuyệt đối không dùng echo "Connected successfully" ở đây)
    return $conn;
}
?>