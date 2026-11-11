<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: api.php (RESTful Web Service / JSON API Engine)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Lost My Buds
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: header('Content-Type: application/json') কেন দেওয়া হয়েছে?
 *    উত্তর: ব্রাউজার বা ক্লায়েন্ট (যেমন মোবাইল অ্যাপ, রিঅ্যাক্ট, বা পাইথন) যাতে বুঝতে পারে
 *          সার্ভার থেকে HTML না পাঠিয়ে পিওর JSON ডাটা পাঠানো হচ্ছে।
 * ২. প্রশ্ন: json_encode() এর কাজ কী?
 *    উত্তর: পিএইচপির অ্যারে বা অবজেক্টকে স্ট্যান্ডার্ড JSON স্ট্রিং এ রূপান্তর করে।
 * ৩. প্রশ্ন: REST API এর গুরুত্ব কী?
 *    উত্তর: এটি ক্লায়েন্ট এবং সার্ভারের মধ্যে লুজ কাপলিং (Loose Coupling) তৈরি করে।
 *          একই PHP ব্যাকএন্ড ডেটা ওয়েবসাইট, অ্যান্ড্রয়েড অ্যাপ এবং আইওএস অ্যাপ সবাই ব্যবহার করতে পারে।
 * ==============================================================================================
 */

// ১. রেসপন্স হেডার কনফিগারেশন
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // ক্রস অরিজিন রিকোয়েস্ট অ্যালাউ করা (CORS)
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// ২. ডাটাবেজ কানেকশন লোড
require_once __DIR__ . '/config/db.php';

// ৩. রিকোয়েস্ট অ্যাকশন শনাক্তকরণ
$action = isset($_GET['action']) ? trim($_GET['action']) : 'stats';

$response = [
    'status' => 'error',
    'timestamp' => date('Y-m-d H:i:s'),
    'module' => 'BudsRecover PHP RESTful Engine'
];

try {
    switch ($action) {

        // ======================= ১. সিস্টেম ওভারভিউ ও পরিসংখ্যান API =======================
        case 'stats':
            $total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
            $total_certs = $pdo->query("SELECT COUNT(*) FROM oem_certificates")->fetchColumn();

            $response['status'] = 'success';
            $response['data'] = [
                'total_active_products' => (int)$total_products,
                'total_orders_placed' => (int)$total_orders,
                'total_oem_certificates' => (int)$total_certs,
                'server_php_version' => phpversion(),
                'server_time' => date('c')
            ];
            break;

        // ======================= ২. পণ্যের ক্যাটালগ API =======================
        case 'products':
            $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 10;
            $stmt = $pdo->prepare("SELECT id, name, brand, model, category, price, original_price, stock, image 
                                   FROM products ORDER BY id DESC LIMIT :lim");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll();

            $response['status'] = 'success';
            $response['count'] = count($products);
            $response['products'] = $products;
            break;

        // ======================= ৩. সিরিয়াল ও OEM ভেরিফিকেশন API =======================
        case 'verify':
            $serial = isset($_GET['serial']) ? trim($_GET['serial']) : '';
            if (empty($serial)) {
                $response['message'] = "সিরিয়াল নম্বর প্যারামিটার (?serial=...) অনুপস্থিত!";
            } else {
                $stmt = $pdo->prepare("SELECT * FROM oem_certificates 
                                       WHERE UPPER(serial_number) = UPPER(:s) 
                                          OR UPPER(certificate_code) = UPPER(:c) 
                                       LIMIT 1");
                $stmt->bindValue(':s', $serial, PDO::PARAM_STR);
                $stmt->bindValue(':c', $serial, PDO::PARAM_STR);
                $stmt->execute();
                $cert = $stmt->fetch();

                if ($cert) {
                    $response['status'] = 'success';
                    $response['is_genuine_oem'] = true;
                    $response['certificate'] = $cert;
                } else {
                    $response['status'] = 'not_found';
                    $response['is_genuine_oem'] = false;
                    $response['message'] = "প্রদত্ত সিরিয়ালের কোনো ভেরিফাইড OEM রেকর্ড পাওয়া যায়নি।";
                }
            }
            break;

        // ======================= ৪. অর্ডার তথ্য API =======================
        case 'order':
            $code = isset($_GET['code']) ? trim($_GET['code']) : '';
            if (empty($code)) {
                $response['message'] = "অর্ডার কোড (?code=...) অনুপস্থিত!";
            } else {
                $stmt = $pdo->prepare("SELECT id, order_code, customer_name, total, delivery_fee, status, created_at 
                                       FROM orders WHERE UPPER(order_code) = UPPER(:c) LIMIT 1");
                $stmt->bindValue(':c', $code, PDO::PARAM_STR);
                $stmt->execute();
                $order = $stmt->fetch();

                if ($order) {
                    $stmt_items = $pdo->prepare("SELECT product_name, category, price, quantity FROM order_items WHERE order_id = :oid");
                    $stmt_items->bindValue(':oid', $order['id'], PDO::PARAM_INT);
                    $stmt_items->execute();
                    $order['items'] = $stmt_items->fetchAll();

                    $response['status'] = 'success';
                    $response['order'] = $order;
                } else {
                    $response['status'] = 'not_found';
                    $response['message'] = "অর্ডার কোডটি সঠিক নয়।";
                }
            }
            break;

        default:
            $response['message'] = "অবৈধ অ্যাকশন! সমর্থিত অ্যাকশনসমূহ: stats, products, verify, order";
            break;
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['error_details'] = $e->getMessage();
}

// ৪. JSON আউটপুট প্রদান
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>
