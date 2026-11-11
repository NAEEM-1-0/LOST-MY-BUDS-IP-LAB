<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: checkout.php (Commercial E-Commerce Checkout & Atomic Order Processing)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Commercial PHP E-Commerce Store
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: চেকআউটে ডাটাবেজ ট্রানজ্যাকশন (Transaction) কীভাবে ম্যানেজ করেছ?
 *    উত্তর: আমরা PDO ট্রানজ্যাকশন ($pdo->beginTransaction()) ব্যবহার করেছি। 
 *          প্রথমে orders টেবিলে অর্ডার এবং order_items টেবিলে প্রতিটি পার্ট ইনসার্ট করে প্রোডাক্ট স্টক
 *          কমিয়ে দেওয়া হয়। সবকিছু সফল হলে $pdo->commit() হয়, কোনো ত্রুটি হলে $pdo->rollBack()
 *          হয়ে ডাটাবেজ আগের সুস্থ অবস্থায় ফেরত যায়।
 * ২. প্রশ্ন: কনকারেন্ট ইউজারদের জন্য স্টক ম্যানেজমেন্ট কীভাবে নিশ্চিত করেছ?
 *    উত্তর: প্রতিটি প্রোডাক্ট ইনসার্টের সময় stock >= qty যাচাই করে UPDATE products SET stock = stock - ?
 *          কোয়েরি এক্সিকিউট করা হয়েছে।
 * ==============================================================================================
 */

$page_title = "নিরাপদ চেকআউট ও ক্যাশ অন ডেলিভারি";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cart_functions.php';

// কার্ট ফাঁকা থাকলে শপে রিডাইরেক্ট
$cart_items = get_cart_items($pdo);
if (empty($cart_items)) {
    $_SESSION['flash_error'] = "আপনার ব্যাগ ফাঁকা! অনুগ্রহ করে চেকআউটের পূর্বে পণ্য যোগ করুন।";
    header("Location: index.php#shop");
    exit;
}

// প্রারম্ভিক ফর্ম ভ্যারিয়েবল
$errors = [];
$name = '';
$phone = '';
$email = '';
$address = '';
$city = 'ঢাকা';
$payment_method = 'Cash on Delivery';
$notes = '';

// ১. চেকআউট ফর্ম সাবমিশন প্রসেসিং ($_POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    
    $name = trim(htmlspecialchars($_POST['name']));
    $phone = trim(htmlspecialchars($_POST['phone']));
    $email = trim(htmlspecialchars($_POST['email']));
    $address = trim(htmlspecialchars($_POST['address']));
    $city = trim(htmlspecialchars($_POST['city']));
    $payment_method = trim(htmlspecialchars($_POST['payment_method']));
    $notes = trim(htmlspecialchars($_POST['notes']));

    // ভ্যালিডেশন
    if (empty($name)) $errors[] = "কাস্টমারের পুরো নাম আবশ্যক।";
    if (empty($phone) || strlen($phone) < 11) $errors[] = "সঠিক ১১ ডিজিটের মোবাইল নম্বর দিন।";
    if (empty($address)) $errors[] = "পূর্ণাঙ্গ ডেলিভারি ঠিকানা প্রদান করুন।";

    // মোট হিসাব রিক্যালকুলেট করা
    $totals = get_cart_totals($pdo, $city);

    // কোনো ভ্যালিডেশন এরর না থাকলে ডাটাবেজ ট্রানজ্যাকশন শুরু
    if (empty($errors)) {
        try {
            // ২. PDO ট্রানজ্যাকশন শুরু (Atomic Safety)
            $pdo->beginTransaction();

            // ৩. প্রতিটি পণ্যের স্টক চূড়ান্তভাবে যাচাই করা
            foreach ($cart_items as $item) {
                $stmt_stk = $pdo->prepare("SELECT stock, name FROM products WHERE id = :id");
                $stmt_stk->bindValue(':id', $item['id'], PDO::PARAM_INT);
                $stmt_stk->execute();
                $cur = $stmt_stk->fetch();

                if (!$cur || $cur['stock'] < $item['quantity']) {
                    throw new Exception("দুঃখিত, '" . $item['name'] . "' এর পর্যাপ্ত স্টক নেই (বর্তমান স্টক: " . ($cur ? $cur['stock'] : 0) . " টি)।");
                }
            }

            // ৪. ইউনিক হিউম্যান-রিডেবল অর্ডার কোড তৈরি (যেমন BR-84920)
            $order_code = "BR-" . rand(10000, 99999);

            // ৫. orders টেবিলে মাস্টার অর্ডার রেকর্ড ইনসার্ট করা
            $stmt_ord = $pdo->prepare("INSERT INTO orders (
                order_code, user_id, total, delivery_fee, payment_method, payment_status,
                customer_name, customer_phone, customer_email, shipping_address, city,
                order_notes, status, coupon_code, discount, created_at
            ) VALUES (
                :ocode, 1, :total, :delivery, :pmethod, 'Unpaid',
                :cname, :cphone, :cemail, :caddress, :ccity,
                :cnotes, 'Pending', :coupon, :discount, datetime('now', 'localtime')
            )");

            $stmt_ord->bindValue(':ocode', $order_code, PDO::PARAM_STR);
            $stmt_ord->bindValue(':total', $totals['grand_total'], PDO::PARAM_STR);
            $stmt_ord->bindValue(':delivery', $totals['delivery_fee'], PDO::PARAM_STR);
            $stmt_ord->bindValue(':pmethod', $payment_method, PDO::PARAM_STR);
            $stmt_ord->bindValue(':cname', $name, PDO::PARAM_STR);
            $stmt_ord->bindValue(':cphone', $phone, PDO::PARAM_STR);
            $stmt_ord->bindValue(':cemail', $email, PDO::PARAM_STR);
            $stmt_ord->bindValue(':caddress', $address, PDO::PARAM_STR);
            $stmt_ord->bindValue(':ccity', $city, PDO::PARAM_STR);
            $stmt_ord->bindValue(':cnotes', $notes, PDO::PARAM_STR);
            $stmt_ord->bindValue(':coupon', $totals['coupon_code'], PDO::PARAM_STR);
            $stmt_ord->bindValue(':discount', $totals['discount'], PDO::PARAM_STR);
            $stmt_ord->execute();

            $order_id = $pdo->lastInsertId();

            // ৬. order_items টেবিলে প্রতিটি আইটেম ইনসার্ট ও স্টক মাইনাস করা
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, category, price, quantity, image) 
                                        VALUES (:oid, :pid, :pname, :pcat, :price, :qty, :image)");

            $stmt_dec = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :pid");

            foreach ($cart_items as $item) {
                // আইটেম ইনসার্ট
                $stmt_item->bindValue(':oid', $order_id, PDO::PARAM_INT);
                $stmt_item->bindValue(':pid', $item['id'], PDO::PARAM_INT);
                $stmt_item->bindValue(':pname', $item['name'], PDO::PARAM_STR);
                $stmt_item->bindValue(':pcat', $item['category'], PDO::PARAM_STR);
                $stmt_item->bindValue(':price', $item['price'], PDO::PARAM_STR);
                $stmt_item->bindValue(':qty', $item['quantity'], PDO::PARAM_INT);
                $stmt_item->bindValue(':image', $item['image'], PDO::PARAM_STR);
                $stmt_item->execute();

                // স্টক হ্রাস
                $stmt_dec->bindValue(':qty', $item['quantity'], PDO::PARAM_INT);
                $stmt_dec->bindValue(':pid', $item['id'], PDO::PARAM_INT);
                $stmt_dec->execute();
            }

            // ৭. ট্রানজ্যাকশন চূড়ান্তভাবে সফল ও পার্মানেন্ট করা
            $pdo->commit();

            // ৮. সেশন কার্ট খালি করা
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon_code']);

            // ৯. অর্ডার সাকসেস পেজে রিডাইরেক্ট (PRG Pattern)
            $_SESSION['flash_success'] = "🎉 অভিনন্দন! আপনার অর্ডার সফলভাবে গ্রহণ করা হয়েছে!";
            header("Location: order_success.php?order_code=" . urlencode($order_code));
            exit;

        } catch (Exception $e) {
            // ত্রুটি হলে সম্পূর্ণ ট্রানজ্যাকশন রোলব্যাক
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "অর্ডার সম্পন্ন করতে সমস্যা হয়েছে: " . $e->getMessage();
        }
    }
}

// ডিফল্ট বা পরিবর্তিত সিটি অনুসারে টোটাল হিসাব
$totals = get_cart_totals($pdo, $city);

// হেডার লোড
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <div>
        <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text-title);">
            📦 নিরাপদ চেকআউট ও অর্ডার কনফার্মেশন
        </h1>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 4px;">
            অনুগ্রহ করে আপনার সঠিক ডেলিভারি ঠিকানা ও মোবাইল নম্বর প্রদান করুন।
        </p>
    </div>

    <!-- কোনো ভ্যালিডেশন এরর থাকলে প্রদর্শন -->
    <?php if (!empty($errors)): ?>
        <div style="background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); border-radius: 10px; padding: 14px 18px;">
            <strong style="color: #fb7185; font-size: 0.95rem;">⚠️ অনুগ্রহ করে নিচের ভুলগুলো সংশোধন করুন:</strong>
            <ul style="color: #fecdd3; font-size: 0.88rem; margin-top: 6px; margin-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- চেকআউট ফর্ম গ্রিড: বামে ফর্ম, ডানে পণ্য রসিদ সামারি -->
    <form action="checkout.php" method="POST" style="display: grid; grid-template-columns: 1fr 380px; gap: 28px; align-items: flex-start;">

        <!-- বাম পাশের ডেলিভারি ফর্ম -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 26px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 18px;">
            
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-title); border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                ১. গ্রাহকের তথ্য ও ডেলিভারি ঠিকানা
            </h3>

            <!-- নাম ও মোবাইল নম্বর -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">
                        আপনার পুরো নাম <span style="color: #f43f5e;">*</span>
                    </label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required
                           placeholder="যেমন: ফারহান আহমেদ"
                           style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: #fff; font-size: 0.92rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">
                        মোবাইল নম্বর <span style="color: #f43f5e;">*</span>
                    </label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required
                           placeholder="০১৭xxxxxxxx"
                           style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: #fff; font-size: 0.92rem;">
                </div>
            </div>

            <!-- ইমেইল ও শহর -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">
                        ইমেইল অ্যাড্রেস
                    </label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                           placeholder="example@gmail.com"
                           style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: #fff; font-size: 0.92rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.84rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">
                        ডেলিভারি এরিয়া / শহর <span style="color: #f43f5e;">*</span>
                    </label>
                    <select name="city" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: #fff; font-size: 0.92rem;">
                        <option value="ঢাকা" <?php echo ($city === 'ঢাকা') ? 'selected' : ''; ?>>ঢাকা শহর (ডেলিভারি ফি: ৳৬০)</option>
                        <option value="ঢাকার বাইরে" <?php echo ($city === 'ঢাকার বাইরে') ? 'selected' : ''; ?>>ঢাকার বাইরে (ডেলিভারি ফি: ৳১২০)</option>
                    </select>
                </div>
            </div>

            <!-- পূর্ণাঙ্গ ঠিকানা -->
            <div>
                <label style="display: block; font-size: 0.84rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">
                    বিস্তারিত ডেলিভারি ঠিকানা <span style="color: #f43f5e;">*</span>
                </label>
                <textarea name="address" rows="3" required
                          placeholder="বাসা নম্বর, রোড নম্বর, এলাকা বা থানার নাম লিখুন..."
                          style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: #fff; font-size: 0.92rem;"><?php echo htmlspecialchars($address); ?></textarea>
            </div>

            <!-- ২. পেমেন্ট মেথড -->
            <div style="margin-top: 10px;">
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-title); border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px; margin-bottom: 12px;">
                    ২. পেমেন্ট পদ্ধতি নির্বাচন করুন
                </h3>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <label style="background: var(--bg-body); border: 2px solid #10b981; padding: 14px 18px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="radio" name="payment_method" value="Cash on Delivery" checked style="accent-color: #10b981; transform: scale(1.2);">
                            <div>
                                <strong style="color: #ffffff; font-size: 0.95rem;">💵 ক্যাশ অন ডেলিভারি (Cash on Delivery)</strong>
                                <div style="font-size: 0.78rem; color: #34d399; margin-top: 2px;">পার্সেল হাতে পেয়ে চেক করে ডেলিভারিম্যানকে টাকা পরিশোধ করবেন।</div>
                            </div>
                        </div>
                        <span style="font-size: 1.5rem;">📦</span>
                    </label>

                    <label style="background: var(--bg-body); border: 1px solid var(--border-subtle); padding: 14px 18px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="radio" name="payment_method" value="bKash / Nagad" style="accent-color: #0284c7; transform: scale(1.2);">
                            <div>
                                <strong style="color: var(--text-title); font-size: 0.95rem;">📱 বিকাশ / নগদ (Mobile Banking)</strong>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">অর্ডার নিশ্চিত করার পর পার্সনাল/মার্চেন্ট নম্বরে পেমেন্ট করবেন।</div>
                            </div>
                        </div>
                        <span style="font-size: 1.5rem;">💳</span>
                    </label>
                </div>
            </div>

            <!-- স্পেশাল নোট -->
            <div>
                <label style="display: block; font-size: 0.84rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">
                    ডেলিভারি নোট বা বিশেষ নির্দেশনা (ঐচ্ছিক)
                </label>
                <input type="text" name="notes" value="<?php echo htmlspecialchars($notes); ?>"
                       placeholder="যেমন: বিকেলে ডেলিভারি করবেন বা ফোন দিয়ে আসবেন..."
                       style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: #fff; font-size: 0.88rem;">
            </div>

        </div>

        <!-- ডান পাশের অর্ডার প্রিভিউ সামারি -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 14px; padding: 24px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 16px;">
            
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-title); border-bottom: 1px solid var(--border-subtle); padding-bottom: 10px;">
                আপনার অর্ডারসমূহ (<?php echo count($cart_items); ?>)
            </h3>

            <!-- পণ্য তালিকা -->
            <div style="display: flex; flex-direction: column; gap: 12px; max-height: 280px; overflow-y: auto;">
                <?php foreach ($cart_items as $item): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; border-bottom: 1px dashed var(--border-subtle); padding-bottom: 10px;">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="" style="width: 40px; height: 40px; object-fit: contain;">
                            <div>
                                <div style="font-weight: 600; color: var(--text-title);"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">পরিমাণ: <?php echo $item['quantity']; ?> টি</div>
                            </div>
                        </div>
                        <div style="font-weight: 700; color: #34d399;">
                            ৳ <?php echo number_format($item['line_total']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- সর্বমোট বিল হিসাব -->
            <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem; margin-top: 8px;">
                <div style="display: flex; justify-content: space-between; color: var(--text-muted);">
                    <span>সাবটোটাল:</span>
                    <span style="font-weight: 600; color: var(--text-title);">৳ <?php echo number_format($totals['subtotal']); ?></span>
                </div>

                <?php if ($totals['discount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; color: #34d399;">
                        <span>কুপন ছাড় (<?php echo htmlspecialchars($totals['coupon_code']); ?>):</span>
                        <span style="font-weight: 700;">- ৳ <?php echo number_format($totals['discount']); ?></span>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; color: var(--text-muted);">
                    <span>ডেলিভারি ফি:</span>
                    <?php if ($totals['delivery_fee'] == 0): ?>
                        <span style="color: #34d399; font-weight: 700;">ফ্রি (FREE)</span>
                    <?php else: ?>
                        <span style="font-weight: 600; color: var(--text-title);">৳ <?php echo number_format($totals['delivery_fee']); ?></span>
                    <?php endif; ?>
                </div>

                <div style="border-top: 2px solid var(--border-subtle); padding-top: 10px; margin-top: 4px; display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 800; color: #38bdf8;">
                    <span>সর্বমোট প্রদেয়:</span>
                    <span>৳ <?php echo number_format($totals['grand_total']); ?></span>
                </div>
            </div>

            <!-- কনফার্ম অর্ডার সাবমিট বাটন -->
            <button type="submit" name="place_order" 
                    style="width: 100%; margin-top: 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 1.1rem; cursor: pointer; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4); transition: transform 0.2s;">
                ✓ অর্ডার সম্পন্ন করুন (Confirm Order)
            </button>

            <div style="font-size: 0.75rem; color: var(--text-muted); text-align: center; line-height: 1.4;">
                🔒 আপনার তথ্য সম্পূর্ণ সুরক্ষিত। অর্ডার সম্পন্ন করার সাথে সাথে ট্র্যাকিং নম্বর জেনারেট হবে।
            </div>

        </div>

    </form>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
