<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: admin_portal.php (Admin Control, Session Auth & State Update)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Lost My Buds
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: সেশন (Session) ও কুকি (Cookie) এর মধ্যে প্রধান পার্থক্য কী?
 *    উত্তর: সেশন ডাটা সার্ভার সাইডে সুরক্ষিতভাবে জমা থাকে, আর কুকি ক্লায়েন্ট/ব্রাউজারে থাকে।
 *          এখানে অ্যাডমিনের লগইন অথেনটিকেশন স্টেট ($_SESSION['admin_auth']) সার্ভার সাইড সেশনে
 *          সংরক্ষিত থাকায় তা ক্লায়েন্ট সাইড থেকে টেম্পার বা পরিবর্তন করা সম্ভব নয়।
 * ২. প্রশ্ন: অ্যাডমিন লগআউট কীভাবে কাজ করে?
 *    উত্তর: session_unset() এবং session_destroy() ফাংশন কল করে বর্তমান সেশনের সব ভ্যারিয়েবল ক্লিয়ার করা হয়।
 * ==============================================================================================
 */

// ১. পেজ টাইটেল ও ডাটাবেজ কানেকশন
$page_title = "পিএইচপি অ্যাডমিন কন্ট্রোল সেন্টার";
require_once __DIR__ . '/config/db.php';

// সেশন স্টার্ট
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ২. লগআউট লজিক
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_auth']);
    unset($_SESSION['admin_user']);
    $_SESSION['flash_success'] = "সফলভাবে অ্যাডমিন প্যানেল থেকে লগআউট সম্পন্ন হয়েছে।";
    header("Location: admin_portal.php");
    exit;
}

// ৩. লগইন অথেনটিকেশন হ্যান্ডলিং ($_POST)
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // ল্যাব প্রজেক্টের স্টুডেন্ট ও শিক্ষকের ডেমোর জন্য ডিফল্ট ক্রেডেনশিয়াল
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_auth'] = true;
        $_SESSION['admin_user'] = 'SuperAdmin (Lab Demo)';
        $_SESSION['flash_success'] = "স্বাগতম! আপনি সফলভাবে পিএইচপি অ্যাডমিন কন্ট্রোলে প্রবেশ করেছেন।";
        header("Location: admin_portal.php");
        exit;
    } else {
        $login_error = "ভুল ইউজারনেম বা পাসওয়ার্ড! অনুগ্রহ করে সঠিক তথ্য দিন।";
    }
}

// ৪. অর্ডার স্ট্যাটাস আপডেট হ্যান্ডলিং (শুধুমাত্র লগইন থাকা অবস্থায়)
if (isset($_SESSION['admin_auth']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = htmlspecialchars(trim($_POST['new_status']));

    try {
        $stmt_up = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
        $stmt_up->bindValue(':status', $new_status, PDO::PARAM_STR);
        $stmt_up->bindValue(':id', $order_id, PDO::PARAM_INT);
        $stmt_up->execute();

        $_SESSION['flash_success'] = "অর্ডার #$order_id এর স্ট্যাটাস পরিবর্তন করে '$new_status' করা হয়েছে!";
        header("Location: admin_portal.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "স্ট্যাটাস আপডেট করতে সমস্যা: " . $e->getMessage();
    }
}

// হেডার ইনক্লুড করা
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <?php if (empty($_SESSION['admin_auth'])): ?>
        <!-- ======================= অ্যাডমিন লগইন ফর্ম ======================= -->
        <div style="max-width: 450px; margin: 40px auto; width: 100%;">
            <div class="card" style="border-top: 4px solid #8b5cf6;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <span style="font-size: 2.5rem;">🔐</span>
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #ffffff; margin-top: 8px;">
                        পিএইচপি অ্যাডমিন লগইন
                    </h2>
                    <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 4px;">
                        PHP সেশন ($_SESSION) অথেনটিকেশন ডেমো
                    </p>
                </div>

                <?php if (!empty($login_error)): ?>
                    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px;">
                        ⚠️ <?php echo htmlspecialchars($login_error); ?>
                    </div>
                <?php endif; ?>

                <form action="admin_portal.php" method="POST" style="display: flex; flex-direction: column; gap: 14px;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 6px; font-weight: 600;">ইউজারনেম</label>
                        <input type="text" name="username" value="admin" required
                               style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem;">
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 6px; font-weight: 600;">পাসওয়ার্ড</label>
                        <input type="password" name="password" value="admin123" required
                               style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem;">
                    </div>

                    <button type="submit" name="login_submit"
                            style="background: linear-gradient(135deg, #8b5cf6, #6366f1); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: 6px;">
                        লগইন করুন (PHP Session)
                    </button>
                </form>

                <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #1e293b; font-size: 0.8rem; color: #94a3b8; text-align: center;">
                    💡 ল্যাব ভাইভা টেস্ট ক্রেডেনশিয়াল: <strong>admin</strong> / <strong>admin123</strong>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ======================= অথেনটিকেটেড অ্যাডমিন ড্যাশবোর্ড ======================= -->
        
        <!-- অ্যাডমিন হেডার ও স্ট্যাটাস বার -->
        <div style="display: flex; justify-content: space-between; align-items: center; background: #1e1b4b; border: 1px solid #3730a3; padding: 16px 24px; border-radius: 12px; flex-wrap: wrap; gap: 12px;">
            <div>
                <span style="font-size: 0.75rem; background: #4338ca; color: #c7d2fe; padding: 2px 8px; border-radius: 4px; font-weight: 700;">
                    ACTIVE SESSION
                </span>
                <h2 style="font-size: 1.35rem; font-weight: 800; color: #ffffff; margin-top: 4px;">
                    👑 অ্যাডমিন কন্ট্রোল প্যানেল (PHP Portal)
                </h2>
                <p style="font-size: 0.82rem; color: #a5b4fc;">
                    লগইনকৃত ইউজার: <strong><?php echo htmlspecialchars($_SESSION['admin_user']); ?></strong> | সেশন আইডি: <code><?php echo session_id(); ?></code>
                </p>
            </div>

            <div>
                <a href="admin_portal.php?action=logout" 
                   style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;">
                    🚪 লগআউট (Logout)
                </a>
            </div>
        </div>

        <?php
        // ডাটাবেজ থেকে অর্ডার এবং সাপোর্ট রিকোয়েস্ট তালিকা লোড করা
        $admin_orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 10")->fetchAll();
        $admin_support = $pdo->query("SELECT * FROM support_requests ORDER BY id DESC LIMIT 10")->fetchAll();
        ?>

        <!-- অর্ডার ম্যানেজমেন্ট ও লাইভ স্ট্যাটাস আপডেট -->
        <div class="card">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #ffffff; margin-bottom: 14px;">
                📦 অর্ডার প্রসেসিং ও স্ট্যাটাস আপডেট (POST & UPDATE Demo)
            </h3>
            <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 16px;">
                ম্যামকে দেখানোর জন্য এখানে সরাসরি যেকোনো অর্ডারের স্ট্যাটাস পরিবর্তন করে ডাটাবেজে আপডেট করা যাবে:
            </p>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.86rem;">
                    <thead>
                        <tr style="background: #1e293b; color: #cbd5e1; border-bottom: 2px solid #334155;">
                            <th style="padding: 10px;">ID</th>
                            <th style="padding: 10px;">অর্ডার কোড</th>
                            <th style="padding: 10px;">কাস্টমার</th>
                            <th style="padding: 10px;">ফোন</th>
                            <th style="padding: 10px;">টাকা</th>
                            <th style="padding: 10px;">বর্তমান স্ট্যাটাস</th>
                            <th style="padding: 10px;">স্ট্যাটাস পরিবর্তন (Update)</th>
                            <th style="padding: 10px; text-align: center;">ইনভয়েস</th>
                        </tr>
                    </thead>
                    <tbody style="color: #e2e8f0;">
                        <?php foreach ($admin_orders as $o): ?>
                            <tr style="border-bottom: 1px solid #1f2937;">
                                <td style="padding: 10px; color: #64748b;">#<?php echo $o['id']; ?></td>
                                <td style="padding: 10px; font-family: monospace; font-weight: 700; color: #38bdf8;"><?php echo htmlspecialchars($o['order_code']); ?></td>
                                <td style="padding: 10px; font-weight: 600;"><?php echo htmlspecialchars($o['customer_name']); ?></td>
                                <td style="padding: 10px; color: #94a3b8;"><?php echo htmlspecialchars($o['customer_phone']); ?></td>
                                <td style="padding: 10px; font-weight: 700; color: #34d399;">৳ <?php echo number_format($o['total']); ?></td>
                                <td style="padding: 10px;">
                                    <span style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: 600;">
                                        <?php echo htmlspecialchars($o['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 10px;">
                                    <!-- স্ট্যাটাস আপডেটের ফর্ম (POST) -->
                                    <form action="admin_portal.php" method="POST" style="display: flex; gap: 6px;">
                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                        <select name="new_status" style="background: #030712; color: #fff; border: 1px solid #334155; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                                            <option value="Pending" <?php echo ($o['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Processing" <?php echo ($o['status'] === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                            <option value="Shipped" <?php echo ($o['status'] === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="Delivered" <?php echo ($o['status'] === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="Cancelled" <?php echo ($o['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_order_status" 
                                                style="background: #0284c7; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 0.78rem; cursor: pointer;">
                                            সেভ
                                        </button>
                                    </form>
                                </td>
                                <td style="padding: 10px; text-align: center;">
                                    <a href="invoice.php?order_id=<?php echo $o['id']; ?>" target="_blank" 
                                       style="color: #38bdf8; text-decoration: underline; font-size: 0.8rem;">
                                        রসিদ ↗
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- জমা পড়া সাপোর্ট ও ওয়ারেন্টি টিকেট টেবিল -->
        <div class="card">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #ffffff; margin-bottom: 14px;">
                📋 জমা পড়া ওয়ারেন্টি ক্লেইম টিকেটসমূহ (From support_requests)
            </h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.86rem;">
                    <thead>
                        <tr style="background: #1e293b; color: #cbd5e1; border-bottom: 2px solid #334155;">
                            <th style="padding: 10px;">টিকেট ID</th>
                            <th style="padding: 10px;">নাম</th>
                            <th style="padding: 10px;">মোবাইল</th>
                            <th style="padding: 10px;">ব্র্যান্ড ও মডেল</th>
                            <th style="padding: 10px;">অংশ</th>
                            <th style="padding: 10px;">বিবরণ ও সংযুক্তি</th>
                            <th style="padding: 10px;">স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody style="color: #e2e8f0;">
                        <?php if (!empty($admin_support)): ?>
                            <?php foreach ($admin_support as $s): ?>
                                <tr style="border-bottom: 1px solid #1f2937;">
                                    <td style="padding: 10px; font-family: monospace; color: #f59e0b;">#TK-<?php echo str_pad($s['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td style="padding: 10px; font-weight: 600;"><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td style="padding: 10px;"><?php echo htmlspecialchars($s['phone']); ?></td>
                                    <td style="padding: 10px;"><?php echo htmlspecialchars($s['brand'] . ' ' . $s['model']); ?></td>
                                    <td style="padding: 10px; color: #38bdf8;"><?php echo htmlspecialchars($s['missing_part']); ?></td>
                                    <td style="padding: 10px; font-size: 0.8rem; color: #cbd5e1; max-width: 250px;">
                                        <?php echo htmlspecialchars($s['message']); ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <span style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-size: 0.75rem; padding: 2px 6px; border-radius: 4px;">
                                            <?php echo htmlspecialchars($s['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="padding: 16px; text-align: center; color: #9ca3af;">কোনো সাপোর্ট টিকেট নেই।</td>
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
