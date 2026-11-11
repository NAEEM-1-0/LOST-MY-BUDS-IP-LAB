<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: track.php (Commercial E-Commerce 4-Stage Live Order Tracking)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Commercial PHP E-Commerce Store
 * ==============================================================================================
 */

$page_title = "লাইভ পার্সেল ট্র্যাকিং ও ডেলিভারি স্ট্যাটাস";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/cart_functions.php';

$query_input = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';
$order = null;
$searched = false;

if (!empty($query_input)) {
    $searched = true;
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders 
                               WHERE UPPER(order_code) = UPPER(:q1) 
                                  OR customer_phone = :q2 
                               ORDER BY id DESC LIMIT 1");
        $stmt->bindValue(':q1', $query_input, PDO::PARAM_STR);
        $stmt->bindValue(':q2', $query_input, PDO::PARAM_STR);
        $stmt->execute();
        $order = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Tracking error: " . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 850px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- হেডার কার্ড ও সার্চ বক্স -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 32px 24px; text-align: center; box-shadow: var(--shadow-sm);">
        <div style="font-size: 2.5rem;">📦</div>
        <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text-title); margin-top: 8px;">
            লাইভ পার্সেল ও ডেলিভারি ট্র্যাকিং
        </h1>
        <p style="color: var(--text-muted); font-size: 0.92rem; margin-top: 4px;">
            আপনার অর্ডার কোড (যেমন: BR-84920) অথবা অর্ডার করার সময় ব্যবহৃত মোবাইল নম্বর দিয়ে অনুসন্ধান করুন।
        </p>

        <form action="track.php" method="GET" style="margin-top: 24px; display: flex; gap: 10px; max-width: 550px; margin-left: auto; margin-right: auto;">
            <input type="text" name="code" value="<?php echo htmlspecialchars($query_input); ?>" 
                   placeholder="অর্ডার নম্বর বা মোবাইল লিখুন..." required
                   style="flex: 1; padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border-subtle); background: var(--bg-body); color: #fff; font-size: 0.95rem; font-family: monospace;">
            <button type="submit" 
                    style="background: linear-gradient(135deg, #0284c7, #2563eb); color: white; border: none; padding: 12px 22px; border-radius: 8px; font-weight: 700; cursor: pointer;">
                ট্র্যাক করুন
            </button>
        </form>

        <div style="margin-top: 14px; font-size: 0.78rem; color: var(--text-muted);">
            💡 টেস্ট ডেমো অর্ডার কোড: <a href="track.php?code=BR-79429" style="color: #38bdf8; text-decoration: underline;">BR-79429</a>
        </div>
    </div>

    <!-- ফলাফল সেকশন -->
    <?php if ($searched): ?>
        <?php if ($order): 
            $status = $order['status'];
            $steps = ['Pending', 'Processing', 'Shipped', 'Delivered'];
            $current_idx = array_search($status, $steps);
            if ($current_idx === false) $current_idx = 0;
        ?>
            <!-- ৪-ধাপের ভিজ্যুয়াল স্টেপার কার্ড -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 32px 24px; box-shadow: var(--shadow-sm);">
                
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">অর্ডার কোড:</div>
                        <div style="font-size: 1.3rem; font-weight: 800; color: #38bdf8; font-family: monospace;"><?php echo htmlspecialchars($order['order_code']); ?></div>
                    </div>
                    <div>
                        <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 700; font-size: 0.85rem; padding: 4px 12px; border-radius: 6px;">
                            বর্তমান স্ট্যাটাস: <?php echo htmlspecialchars($status); ?>
                        </span>
                    </div>
                </div>

                <!-- ভিজ্যুয়াল ৪-ধাপের স্টেপার -->
                <div style="margin: 36px 0; display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; position: relative; text-align: center;">
                    
                    <?php 
                    $step_meta = [
                        ['icon' => '📝', 'title' => 'অর্ডার গৃহীত', 'code' => 'Pending'],
                        ['icon' => '📦', 'title' => 'প্যাকেজিং ও টেস্ট', 'code' => 'Processing'],
                        ['icon' => '🚚', 'title' => 'কুরিয়ারে হস্তান্তর', 'code' => 'Shipped'],
                        ['icon' => '🏠', 'title' => 'ডেলিভারি সম্পন্ন', 'code' => 'Delivered']
                    ];
                    foreach ($step_meta as $idx => $sm): 
                        $is_done = ($idx <= $current_idx);
                        $is_current = ($idx === $current_idx);
                    ?>
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; 
                                        background: <?php echo $is_done ? '#10b981' : '#1e293b'; ?>; 
                                        color: <?php echo $is_done ? '#022c22' : '#64748b'; ?>; 
                                        font-size: 1.4rem; display: flex; align-items: center; justify-content: center; font-weight: 800; margin-bottom: 8px;
                                        border: <?php echo $is_current ? '3px solid #38bdf8' : 'none'; ?>; box-shadow: <?php echo $is_current ? '0 0 16px rgba(56,189,248,0.5)' : 'none'; ?>;">
                                <?php echo $sm['icon']; ?>
                            </div>
                            <div style="font-weight: 700; font-size: 0.85rem; color: <?php echo $is_done ? 'var(--text-title)' : 'var(--text-muted)'; ?>;">
                                <?php echo $sm['title']; ?>
                            </div>
                            <div style="font-size: 0.72rem; color: <?php echo $is_done ? '#34d399' : 'var(--text-muted)'; ?>; margin-top: 2px;">
                                <?php echo $is_done ? '✓ সম্পন্ন' : 'অপেক্ষমান'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- পার্সেল ও ডেলিভারি বিবরণ -->
                <div style="background: var(--bg-body); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; font-size: 0.88rem;">
                    <div>
                        <span style="color: var(--text-muted); font-size: 0.78rem;">গ্রাহকের নাম:</span>
                        <div style="font-weight: 700; color: var(--text-title);"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); font-size: 0.78rem;">মোবাইল নম্বর:</span>
                        <div style="font-weight: 700; color: var(--text-title);"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); font-size: 0.78rem;">ডেলিভারি ঠিকানা:</span>
                        <div style="font-weight: 600; color: var(--text-title);"><?php echo htmlspecialchars($order['shipping_address']); ?>, <?php echo htmlspecialchars($order['city']); ?></div>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); font-size: 0.78rem;">কুরিয়ার পার্টনার:</span>
                        <div style="font-weight: 700; color: #38bdf8;">Steadfast Express / Pathao Logistics</div>
                    </div>
                </div>

                <div style="margin-top: 20px; text-align: center;">
                    <a href="invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank" 
                       style="background: #0284c7; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 0.88rem; text-decoration: none;">
                        🖨️ এই অর্ডারের ইনভয়েস প্রিন্ট করুন &rarr;
                    </a>
                </div>

            </div>

        <?php else: ?>
            <div style="background: var(--bg-card); border: 1px solid rgba(244, 63, 94, 0.3); border-radius: 14px; padding: 30px; text-align: center;">
                <div style="font-size: 2.5rem;">🔍</div>
                <h3 style="color: #fb7185; font-size: 1.2rem; font-weight: 700; margin-top: 8px;">কোনো অর্ডার রেকর্ড পাওয়া যায়নি</h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 4px;">অনুগ্রহ করে সঠিক অর্ডার কোড অথবা ১১ ডিজিটের মোবাইল নম্বর দিন।</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
