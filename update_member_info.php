<?php
session_start();
include("db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['uid']) && $_SESSION['role'] == 4) {
    $uid = $_SESSION['uid'];

    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $skills = isset($_POST['skills']) ? array_map('trim', explode(',', $_POST['skills'])) : [];

    $res = $conn->query("SELECT m_data FROM member WHERE m_id = $uid");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $data = json_decode($row['m_data'], true);

        // Update values
        $data['email'] = $email;
        $data['role'] = $role;
        $data['skills'] = $skills;

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("UPDATE member SET m_data = ? WHERE m_id = ?");
        $stmt->bind_param("si", $json, $uid);
        $stmt->execute();
    }
}

header("Location: team_dashboard.php");
exit;