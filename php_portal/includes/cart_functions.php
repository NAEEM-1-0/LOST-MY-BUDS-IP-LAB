<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: cart_functions.php (PHP Session-Based Commercial Cart Engine)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Commercial PHP E-Commerce Store
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: ইকমার্স সাইটে শপিং কার্টের ডাটা কোথায় এবং কীভাবে সংরক্ষণ করা হয়েছে?
 *    উত্তর: পিএইচপি সার্ভার-সাইড সেশন ($_SESSION['cart']) এ অ্যারে হিসেবে [product_id => quantity] 
 *          ফরম্যাটে সেভ থাকে। ফলে পেজ রিলোড বা নেভিগেট করলেও কার্টের ডাটা অক্ষুণ্ণ থাকে।
 * ২. প্রশ্ন: কুপন ডিসকাউন্ট ও ডেলিভারি চার্জ কীভাবে ক্যালকুলেট হয়?
 *    উত্তর: get_cart_totals() ফাংশনে সাবটোটালের ওপর ভিত্তি করে কুপন কোড (BUDS10, FREESHIP, SAVE500) 
 *          এবং কাস্টমারের শহরের (ঢাকা vs ঢাকার বাইরে) ডেলিভারি ফি ডায়নামিকভাবে হিসাব করা হয়।
 * ==============================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// কার্ট সেশন ইনিশিয়ালাইজেশন
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/**
 * কার্টে মোট কতটি আইটেম আছে তা গণনা করে
 */
function get_cart_count() {
    $count = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $qty) {
            $count += (int)$qty;
        }
    }
    return $count;
}

/**
 * কার্টে প্রোডাক্ট যুক্ত করা
 */
function add_to_cart($product_id, $quantity = 1) {
    $product_id = (int)$product_id;
    $quantity = max(1, (int)$quantity);

    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

/**
 * কার্টে প্রোডাক্টের পরিমাণ আপডেট করা
 */
function update_cart_quantity($product_id, $quantity) {
    $product_id = (int)$product_id;
    $quantity = (int)$quantity;

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$product_id]);
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

/**
 * কার্ট থেকে প্রোডাক্ট মুছে ফেলা
 */
function remove_from_cart($product_id) {
    $product_id = (int)$product_id;
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
}

/**
 * ডাটাবেজ থেকে কার্টের প্রতিটি পণ্যের বিস্তারিত তথ্য ও মোট দাম হিসাব করা
 */
function get_cart_items($pdo) {
    $items = [];
    if (empty($_SESSION['cart'])) {
        return $items;
    }

    $placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $ids = array_keys($_SESSION['cart']);

    try {
        $stmt = $pdo->prepare("SELECT id, name, brand, model, category, price, original_price, image, stock 
                               FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $p) {
            $pid = $p['id'];
            $qty = (int)$_SESSION['cart'][$pid];
            $line_total = $p['price'] * $qty;

            $items[] = [
                'id' => $pid,
                'name' => $p['name'],
                'brand' => $p['brand'],
                'model' => $p['model'],
                'category' => $p['category'],
                'price' => (float)$p['price'],
                'original_price' => (float)$p['original_price'],
                'image' => $p['image'],
                'stock' => (int)$p['stock'],
                'quantity' => $qty,
                'line_total' => $line_total
            ];
        }
    } catch (PDOException $e) {
        error_log("Cart items fetch error: " . $e->getMessage());
    }

    return $items;
}

/**
 * কার্টের সর্বমোট সাবটোটাল, ডিসকাউন্ট, ডেলিভারি চার্জ ও গ্র্যান্ড টোটাল হিসাব করা
 */
function get_cart_totals($pdo, $city = 'ঢাকা') {
    $items = get_cart_items($pdo);
    $subtotal = 0;

    foreach ($items as $item) {
        $subtotal += $item['line_total'];
    }

    // ডেলিভারি চার্জ নীতি: ঢাকা = ৬০ টাকা, ঢাকার বাইরে = ১২০ টাকা
    $delivery_fee = (stripos($city, 'ঢাকা') !== false || stripos($city, 'dhaka') !== false) ? 60 : 120;

    // সাবটোটাল ৫০০০ টাকা বা তার বেশি হলে স্বয়ংক্রিয় ফ্রি ডেলিভারি!
    $free_shipping = false;
    if ($subtotal >= 5000 && $subtotal > 0) {
        $delivery_fee = 0;
        $free_shipping = true;
    }

    // কুপন ডিসকাউন্ট হিসাব
    $coupon_code = isset($_SESSION['coupon_code']) ? strtoupper(trim($_SESSION['coupon_code'])) : '';
    $discount = 0;

    if (!empty($coupon_code)) {
        if ($coupon_code === 'BUDS10') {
            $discount = round($subtotal * 0.10); // ১০% ডিসকাউন্ট
        } elseif ($coupon_code === 'SAVE500') {
            $discount = min(500, $subtotal); // ফ্ল্যাট ৫০০ টাকা ছাড়
        } elseif ($coupon_code === 'FREESHIP') {
            $delivery_fee = 0; // ফ্রি ডেলিভারি
        }
    }

    $grand_total = max(0, ($subtotal - $discount) + $delivery_fee);

    return [
        'subtotal' => $subtotal,
        'delivery_fee' => $delivery_fee,
        'free_shipping' => $free_shipping,
        'coupon_code' => $coupon_code,
        'discount' => $discount,
        'grand_total' => $grand_total,
        'item_count' => count($items)
    ];
}
?>
