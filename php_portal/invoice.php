<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: invoice.php (Dynamic Order Invoice & Billing Engine)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Lost My Buds
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: ইনভয়েস তৈরিতে পিএইচপিতে কীভাবে রিলেশনাল ডাটা কোয়েরি করা হয়েছে?
 *    উত্তর: orders টেবিলের মূল তথ্যের সাথে order_items টেবিলের রেকর্ডগুলোকে JOIN/Foreign Key (order_id)
 *          দিয়ে কোয়েরি করা হয়েছে। এরপর লুপ (foreach) চালিয়ে আইটেম ভিত্তিক সাবটোটাল ও গ্র্যান্ড টোটাল
 *          হিসাব করা হয়েছে।
 * ২. প্রশ্ন: URL এ ইনপুট হিসেবে আসা order_id কে কীভাবে নিরাপদ করা হয়েছে?
 *    উত্তর: intval() অথবা filter_var($_GET['order_id'], FILTER_VALIDATE_INT) ব্যবহার করে কাস্টিং করা হয়েছে, 
 *          যাতে কেউ কোনো স্ট্রিং বা এসকিউএল ইঞ্জেকশন কোড পাঠাতে না পারে।
 * ==============================================================================================
 */

// ১. পেজ টাইটেল ও ডাটাবেজ কানেকশন লোড
$page_title = "অর্ডার ইনভয়েস ও রসিদ জেনারেটর";
require_once __DIR__ . '/config/db.php';

// ২. ইনপুট স্যানিটাইজেশন: GET প্যারামিটার থেকে order_id সংগ্রহ
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order_code = isset($_GET['order_code']) ? trim($_GET['order_code']) : '';

$order = null;
$order_items = [];

// ৩. অর্ডার তথ্য ডাটাবেজ থেকে ফেচ করা
if ($order_id > 0 || !empty($order_code)) {
    try {
        if ($order_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :oid LIMIT 1");
            $stmt->bindValue(':oid', $order_id, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE UPPER(order_code) = UPPER(:ocode) LIMIT 1");
            $stmt->bindValue(':ocode', $order_code, PDO::PARAM_STR);
        }
        $stmt->execute();
        $order = $stmt->fetch();

        // অর্ডার পাওয়া গেলে তার অন্তর্গত আইটেমস নিয়ে আসা
        if ($order) {
            $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :oid");
            $stmt_items->bindValue(':oid', $order['id'], PDO::PARAM_INT);
            $stmt_items->execute();
            $order_items = $stmt_items->fetchAll();
        }
    } catch (PDOException $e) {
        die("ইনভয়েস ডাটা ফেচ করতে সমস্যা: " . $e->getMessage());
    }
}

// কোনো অর্ডার আইডি সিলেক্ট না করা থাকলে সাম্প্রতিক অর্ডারের তালিকা ফেচ করা
$all_orders = [];
if (!$order) {
    $stmt_all = $pdo->query("SELECT id, order_code, customer_name, customer_phone, total, status, created_at FROM orders ORDER BY id DESC LIMIT 15");
    $all_orders = $stmt_all->fetchAll();
}

// হেডার ইনক্লুড করা
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* প্রিন্টিং স্টাইলশীট: শুধু ইনভয়েস অংশটি পেপারে প্রিন্ট হবে */
    @media print {
        body {
            background: #ffffff !important;
            color: #000000 !important;
        }
        .navbar, .academic-badge-bar, .academic-footer, .no-print, .alert {
            display: none !important;
        }
        .main-container {
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
        }
        .invoice-card {
            box-shadow: none !important;
            border: 1px solid #000 !important;
            background: #fff !important;
            color: #000 !important;
        }
        .invoice-card * {
            color: #000000 !important;
        }
    }
</style>

<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <?php if ($order): ?>
        <!-- ইনভয়েস ভিউ সেকশন -->
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 14px 20px; border-radius: 10px;">
            <a href="invoice.php" style="color: #94a3b8; text-decoration: none; font-size: 0.9rem;">
                &larr; অন্যান্য অর্ডারের তালিকা দেখুন
            </a>
            <button onclick="window.print()" 
                    style="background: #0284c7; color: white; border: none; padding: 8px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                🖨️ ইনভয়েস প্রিন্ট করুন (Print / PDF)
            </button>
        </div>

        <!-- মূল ইনভয়েস কার্ড -->
        <div class="card invoice-card" style="background: #0b1120; border: 1px solid #334155; padding: 36px; border-radius: 12px;">
            
            <!-- ইনভয়েস হেডার -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1e293b; padding-bottom: 24px; flex-wrap: wrap; gap: 20px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 2rem;">🎧</span>
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; color: #ffffff;">BudsRecover Ltd.</h2>
                            <p style="font-size: 0.82rem; color: #94a3b8;">Original OEM Single Earbud Replacements</p>
                        </div>
                    </div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-top: 8px; line-height: 1.4;">
                        লেভেল ৪, সফটওয়্যার টেকনোলজি পার্ক, কাওরান বাজার, ঢাকা<br>
                        হেল্পলাইন: +৮৮০১৭১২-LOSTBUD | ইমেইল: billing@budsrecover.com
                    </p>
                </div>

                <div style="text-align: right;">
                    <div style="font-size: 0.85rem; color: #38bdf8; font-weight: 700; text-transform: uppercase;">অফিসিয়াল ইনভয়েস</div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: #ffffff; font-family: monospace;">
                        #<?php echo htmlspecialchars($order['order_code']); ?>
                    </div>
                    <div style="font-size: 0.82rem; color: #94a3b8; margin-top: 4px;">
                        তারিখ: <?php echo htmlspecialchars(date("d M, Y h:i A", strtotime($order['created_at']))); ?>
                    </div>
                    <div style="margin-top: 6px;">
                        <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 4px;">
                            <?php echo htmlspecialchars($order['payment_status']); ?> (<?php echo htmlspecialchars($order['payment_method']); ?>)
                        </span>
                    </div>
                </div>
            </div>

            <!-- বিলিং ও শিপিং ইনফরমেশন -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px; padding-bottom: 20px; border-bottom: 1px solid #1e293b;">
                <div>
                    <h4 style="font-size: 0.82rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 6px;">বিল গ্রহীতার বিবরণ (Customer)</h4>
                    <div style="font-size: 1.05rem; font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                    <div style="font-size: 0.88rem; color: #cbd5e1; margin-top: 2px;">ফোন: <?php echo htmlspecialchars($order['customer_phone']); ?></div>
                    <?php if (!empty($order['customer_email'])): ?>
                        <div style="font-size: 0.88rem; color: #94a3b8;">ইমেইল: <?php echo htmlspecialchars($order['customer_email']); ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <h4 style="font-size: 0.82rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 6px;">শিপিং ডেলিভারি ঠিকানা</h4>
                    <div style="font-size: 0.95rem; color: #cbd5e1; line-height: 1.5;">
                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?><br>
                        শহর: <strong><?php echo htmlspecialchars($order['city']); ?></strong>
                    </div>
                    <div style="font-size: 0.82rem; color: #94a3b8; margin-top: 4px;">
                        ডেলিভারি স্ট্যাটাস: <strong style="color: #38bdf8;"><?php echo htmlspecialchars($order['status']); ?></strong>
                    </div>
                </div>
            </div>

            <!-- অর্ডারের পণ্য তালিকা টেবিল (PHP foreach লুপ) -->
            <div style="margin-top: 24px;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #334155; color: #94a3b8;">
                            <th style="padding: 10px 0;">আইটেম ও বিবরণ</th>
                            <th style="padding: 10px 0; text-align: center;">অংশ (Part)</th>
                            <th style="padding: 10px 0; text-align: center;">পরিমাণ</th>
                            <th style="padding: 10px 0; text-align: right;">একক মূল্য</th>
                            <th style="padding: 10px 0; text-align: right;">মোট টাকা</th>
                        </tr>
                    </thead>
                    <tbody style="color: #e2e8f0;">
                        <?php 
                        $calc_subtotal = 0;
                        if (!empty($order_items)): 
                            foreach ($order_items as $item): 
                                $line_total = $item['price'] * $item['quantity'];
                                $calc_subtotal += $line_total;
                        ?>
                            <tr style="border-bottom: 1px solid #1e293b;">
                                <td style="padding: 12px 0; font-weight: 600; color: #ffffff;">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </td>
                                <td style="padding: 12px 0; text-align: center;">
                                    <span style="background: rgba(56, 189, 248, 0.1); color: #38bdf8; font-size: 0.78rem; padding: 2px 8px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($item['category']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 0; text-align: center; font-weight: 700; color: #ffffff;">
                                    <?php echo htmlspecialchars($item['quantity']); ?>
                                </td>
                                <td style="padding: 12px 0; text-align: right; color: #94a3b8;">
                                    ৳ <?php echo number_format($item['price'], 2); ?>
                                </td>
                                <td style="padding: 12px 0; text-align: right; font-weight: 700; color: #ffffff;">
                                    ৳ <?php echo number_format($line_total, 2); ?>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- সর্বমোট গাণিতিক হিসাব সেকশন -->
            <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                <div style="width: 320px; display: flex; flex-direction: column; gap: 8px; font-size: 0.92rem;">
                    <div style="display: flex; justify-content: space-between; color: #94a3b8;">
                        <span>পণ্য উপমোট (Subtotal):</span>
                        <span style="font-weight: 600; color: #ffffff;">৳ <?php echo number_format($calc_subtotal, 2); ?></span>
                    </div>

                    <?php if (!empty($order['discount']) && $order['discount'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; color: #34d399;">
                            <span>কুপন ছাড় (<?php echo htmlspecialchars($order['coupon_code']); ?>):</span>
                            <span>- ৳ <?php echo number_format($order['discount'], 2); ?></span>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; color: #94a3b8;">
                        <span>হোম ডেলিভারি ফি:</span>
                        <span style="font-weight: 600; color: #ffffff;">৳ <?php echo number_format($order['delivery_fee'], 2); ?></span>
                    </div>

                    <div style="border-top: 2px solid #334155; padding-top: 10px; margin-top: 4px; display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 800; color: #38bdf8;">
                        <span>সর্বমোট প্রদেয়:</span>
                        <span>৳ <?php echo number_format($order['total'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- ইনভয়েস ফুটার ও ওয়ারেন্টি নোট -->
            <div style="margin-top: 32px; padding-top: 18px; border-top: 1px dashed #334155; font-size: 0.8rem; color: #64748b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    🛡️ <strong>ওয়ারেন্টি নির্দেশিকা:</strong> ক্রয়ের তারিখ থেকে ৯০ দিন পর্যন্ত ১০০% আনকন্ডিশনাল রিপ্লেসমেন্ট ওয়ারেন্টি প্রযোজ্য।
                </div>
                <div style="font-family: monospace; color: #38bdf8;">
                    Auth ID: BR-SEC-<?php echo md5($order['id'] . $order['order_code']); ?>
                </div>
            </div>

        </div>

    <?php else: ?>

        <!-- কোনো নির্দিষ্ট অর্ডার সিলেক্ট না থাকলে অর্ডার ব্রাউজার তালিকা -->
        <div class="card" style="border-top: 4px solid #0284c7;">
            <span style="background: rgba(2, 132, 199, 0.15); color: #38bdf8; font-weight: 700; font-size: 0.75rem; padding: 4px 10px; border-radius: 6px;">
                PHP Dynamic Invoice Engine
            </span>
            <h1 style="font-size: 1.7rem; font-weight: 800; margin-top: 10px; color: #ffffff;">
                🧾 অর্ডার ইনভয়েস ও রসিদ ব্রাউজার
            </h1>
            <p style="color: #94a3b8; font-size: 0.95rem; margin-top: 6px;">
                ইনভয়েস দেখতে নিচের যেকোনো অর্ডারের 'ইনভয়েস ভিউ' বাটনে ক্লিক করুন অথবা অর্ডার নম্বর সার্চ করুন:
            </p>

            <!-- অর্ডার সার্চ ফর্ম -->
            <form action="invoice.php" method="GET" style="margin-top: 18px; display: flex; gap: 10px; max-width: 500px;">
                <input type="text" name="order_code" placeholder="অর্ডার কোড যেমন: BR-79429" required
                       style="flex: 1; padding: 12px 16px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem; font-family: monospace;">
                <button type="submit" 
                        style="background: #0284c7; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 700; cursor: pointer;">
                    সার্চ (GET)
                </button>
            </form>

            <!-- সাম্প্রতিক অর্ডারসমূহের টেবিল -->
            <div style="margin-top: 24px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
                    <thead>
                        <tr style="background: #1e293b; color: #cbd5e1; border-bottom: 2px solid #334155;">
                            <th style="padding: 10px;">ID</th>
                            <th style="padding: 10px;">অর্ডার কোড</th>
                            <th style="padding: 10px;">গ্রাহকের নাম</th>
                            <th style="padding: 10px;">ফোন নম্বর</th>
                            <th style="padding: 10px;">মোট মূল্য</th>
                            <th style="padding: 10px;">স্ট্যাটাস</th>
                            <th style="padding: 10px; text-align: center;">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody style="color: #e2e8f0;">
                        <?php if (!empty($all_orders)): ?>
                            <?php foreach ($all_orders as $ord): ?>
                                <tr style="border-bottom: 1px solid #1f2937;">
                                    <td style="padding: 10px; color: #6b7280;">#<?php echo $ord['id']; ?></td>
                                    <td style="padding: 10px; font-family: monospace; font-weight: 700; color: #38bdf8;">
                                        <?php echo htmlspecialchars($ord['order_code']); ?>
                                    </td>
                                    <td style="padding: 10px; font-weight: 600;"><?php echo htmlspecialchars($ord['customer_name']); ?></td>
                                    <td style="padding: 10px; color: #94a3b8;"><?php echo htmlspecialchars($ord['customer_phone']); ?></td>
                                    <td style="padding: 10px; font-weight: 700; color: #34d399;">৳ <?php echo number_format($ord['total']); ?></td>
                                    <td style="padding: 10px;">
                                        <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px;">
                                            <?php echo htmlspecialchars($ord['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <a href="invoice.php?order_id=<?php echo $ord['id']; ?>" 
                                           style="background: #0284c7; color: white; text-decoration: none; padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                            ইনভয়েস ভিউ &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="padding: 16px; text-align: center; color: #9ca3af;">এখনো কোনো অর্ডার প্লেস করা হয়নি।</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
