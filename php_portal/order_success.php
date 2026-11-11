<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: order_success.php (Commercial E-Commerce Order Confirmation & Receipt)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Commercial PHP E-Commerce Store
 * ==============================================================================================
 */

$page_title = "অর্ডার সফলভাবে সম্পন্ন হয়েছে!";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cart_functions.php';

$order_code = isset($_GET['order_code']) ? trim($_GET['order_code']) : '';
$order = null;
$order_items = [];

if (!empty($order_code)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE UPPER(order_code) = UPPER(:c) LIMIT 1");
        $stmt->bindValue(':c', $order_code, PDO::PARAM_STR);
        $stmt->execute();
        $order = $stmt->fetch();

        if ($order) {
            $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :oid");
            $stmt_items->bindValue(':oid', $order['id'], PDO::PARAM_INT);
            $stmt_items->execute();
            $order_items = $stmt_items->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Order fetch error: " . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 800px; margin: 20px auto; display: flex; flex-direction: column; gap: 24px;">

    <?php if ($order): ?>
        <!-- সফল অর্ডার কার্ড -->
        <div style="background: var(--bg-card); border: 2px solid #10b981; border-radius: 20px; padding: 40px 30px; text-align: center; box-shadow: var(--shadow-md);">
            
            <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 3rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 0 30px rgba(16, 185, 129, 0.4);">
                ✓
            </div>

            <span style="background: #10b981; color: #022c22; font-weight: 800; font-size: 0.75rem; padding: 4px 12px; border-radius: 20px; text-transform: uppercase;">
                অর্ডার নিশ্চিতকরণ সফল
            </span>

            <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-title); margin-top: 12px;">
                ধন্যবাদ, আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে!
            </h1>

            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 8px; max-width: 580px; margin-left: auto; margin-right: auto;">
                আমাদের কাস্টমার কেয়ার টিম খুব দ্রুত আপনার সাথে ফোনে যোগাযোগ করবে এবং পার্সেলটি প্যাকেজিং সম্পন্ন করে কুরিয়ারে হস্তান্তর করবে।
            </p>

            <!-- অর্ডার কোড ও সামারি স্ট্রিপ -->
            <div style="background: var(--bg-body); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 20px; margin: 28px 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; text-align: left;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">অর্ডার ট্র্যাকিং নম্বর:</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #38bdf8; font-family: monospace;"><?php echo htmlspecialchars($order['order_code']); ?></div>
                </div>

                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">মোট প্রদেয় টাকা:</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #34d399;">৳ <?php echo number_format($order['total']); ?></div>
                </div>

                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">পেমেন্ট পদ্ধতি:</div>
                    <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-title);"><?php echo htmlspecialchars($order['payment_method']); ?></div>
                </div>

                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">ডেলিভারি ঠিকানা:</div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-title);"><?php echo htmlspecialchars($order['city']); ?></div>
                </div>
            </div>

            <!-- অ্যাকশন বাটনসমূহ -->
            <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
                <a href="invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank"
                   style="background: #0284c7; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px;">
                    🖨️ অফিসিয়াল ইনভয়েস প্রিন্ট করুন
                </a>

                <a href="track.php?code=<?php echo urlencode($order['order_code']); ?>"
                   style="background: var(--bg-elevated); color: var(--text-title); border: 1px solid var(--border-subtle); padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px;">
                    📦 পার্সেল ট্র্যাকিং দেখুন
                </a>

                <a href="index.php#shop" 
                   style="background: transparent; color: #38bdf8; padding: 12px 18px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; text-decoration: underline;">
                    হোম স্টোরে ফিরে যান &rarr;
                </a>
            </div>

        </div>

    <?php else: ?>
        <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 40px; text-align: center;">
            <div style="font-size: 3rem;">⚠️</div>
            <h2 style="font-size: 1.4rem; font-weight: 700; color: var(--text-title); margin-top: 10px;">অর্ডার কোড পাওয়া যায়নি</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">অনুগ্রহ করে সঠিক অর্ডার কোড নিয়ে চেষ্টা করুন।</p>
            <div style="margin-top: 20px;">
                <a href="index.php" style="background: #0284c7; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 700;">হোমে ফিরে যান</a>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
