<?php
require_once __DIR__ . "/core/bootstrap.php";
$db = Database::getConnection();

$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxMCIsImp0aSI6IjRhZGVlNWIwNDg5NzIwZDFkNTEyZTA5Zjk4NTk5OTUwZmFlNDFjZjkyZDgxNDhhMzE2NzNmYmE4MjQyYjkzYzY4ODI3NDU2M2EzZWQ3YmJlIiwiaWF0IjoxNzkwMDAyNzIxLjM5NTM4NSwibmJmIjoxNzkwMDAyNzIxLjM5NTM4NywiZXhwIjo0OTQ1Njc2MzIxLjM5MDA1Niwic3ViIjoiNTMwIiwic2NvcGVzIjpbXX0.rMZ_jwkHhFe6yml_l-ufiEhRGYo5g5WyTkbdk3LLcwRPHymNMOQ6_HRVPW1VPG6NfXqltPduUDXF8iE0twGAn-Hyv0GxsRzjSpDYY2k4I9J1XjpcC-hMonkJrMy7V9cPjORi-vusl0A6QS5nCf58bx4wXMN-8QAZQZ0U3wXzxLntxHnkBIl79e0moCFv0lDb7xftGeSBVhrgDGLd05238eM_58FNBazMd9EpaO4T1p8uHwFVH8EBOSLJMmbxWjE8j6ScNJgtPK7alianNQdrewVvnEvO9RKrL3hUZcCI7OzZCqP6hf0-YgbWwuNsz3aU_KSCM6OAfMd04GRLjmNCwc5zcmSkZ8qDjtpWRxbqBS89dwTWpyYRKrTveJ2D09LhRvFpls3m4HPq1fGhzZwN_uHqz7JBVY2tKHj_cOMtbK5WtQVxLXmpARo1KsCPCqIPkpE4SNmQtM8ABt1wlLKUdeMHl5g-Hicirlkqec5bWcB_2BdHgnN5IPp-BMx_ZssbR92xmRvRlOXoSPcLEW3dZKrxzH9XLi34EV5BfvR6nMPgcuVAz9V14WmI7pZuzfUYkYhok4BgcLLdZkWJr96-6byVIAB4ABwrJwU31Mn_3n7Aq4WYwDtODTVy4khwlbM6MqGV3CtNeJdRKWuS_ZCa0d9KZuNsm5RnDYFAKr-sC7w";

$stmt = $db->prepare("UPDATE system_settings SET setting_value = :val WHERE setting_key = 'leajlak_api_token'");
$stmt->execute([":val" => $token]);

if ($stmt->rowCount() === 0) {
    $stmtInsert = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('leajlak_api_token', :val)");
    $stmtInsert->execute([":val" => $token]);
}

echo "SUCCESS";

