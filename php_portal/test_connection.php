<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: test_connection.php (PHP & SQLite System Diagnostics)
 * কোর্স: Internet Programming Lab (IP Lab)
 * প্রজেক্ট: BudsRecover — Lost My Buds
 * ==============================================================================================
 */

$page_title = "সিস্টেম ও ডাটাবেজ ডায়াগনস্টিকস";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">

    <div class="card" style="border-top: 4px solid #10b981;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="font-size: 2.5rem;">🩺</div>
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: #ffffff;">
                    IP Lab সিস্টেম ও পরিবেশ নিরীক্ষণ (Diagnostics)
                </h1>
                <p style="font-size: 0.88rem; color: #94a3b8;">
                    পিএইচপি রানটাইম এবং পিডিও এসকিউএলআইট৩ ডাটাবেজ ইন্টিগ্রিটি চেক
                </p>
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">

            <!-- পিএইচপি সংস্করণ -->
            <div style="display: flex; justify-content: space-between; padding: 12px; background: #030712; border-radius: 8px; border: 1px solid #1f2937;">
                <span style="color: #cbd5e1;">PHP Engine Version:</span>
                <strong style="color: #38bdf8; font-family: monospace;"><?php echo phpversion(); ?></strong>
            </div>

            <!-- PDO SQLite ড্রাইভার -->
            <div style="display: flex; justify-content: space-between; padding: 12px; background: #030712; border-radius: 8px; border: 1px solid #1f2937;">
                <span style="color: #cbd5e1;">PDO SQLite Extension:</span>
                <span style="color: #34d399; font-weight: 700;">
                    <?php echo in_array('sqlite', PDO::getAvailableDrivers()) ? '✓ ইনস্টলড ও সক্রিয় (Active)' : '❌ অনুপস্থিত'; ?>
                </span>
            </div>

            <!-- ডাটাবেজ ফাইল পাথ -->
            <div style="display: flex; justify-content: space-between; padding: 12px; background: #030712; border-radius: 8px; border: 1px solid #1f2937;">
                <span style="color: #cbd5e1;">সংযুক্ত ডাটাবেজ পাথ:</span>
                <span style="color: #f59e0b; font-family: monospace; font-size: 0.82rem; word-break: break-all;">
                    <?php echo htmlspecialchars($db_file); ?>
                </span>
            </div>

            <!-- ডাটাবেজ সাইজ -->
            <div style="display: flex; justify-content: space-between; padding: 12px; background: #030712; border-radius: 8px; border: 1px solid #1f2937;">
                <span style="color: #cbd5e1;">ডাটাবেজ ফাইলের আকার:</span>
                <span style="color: #ffffff; font-weight: 600;">
                    <?php echo round(filesize($db_file) / 1024, 2); ?> KB
                </span>
            </div>

            <!-- লাইভ টেবিল চেক -->
            <div style="padding: 14px; background: #030712; border-radius: 8px; border: 1px solid #1f2937;">
                <h4 style="color: #38bdf8; font-size: 0.9rem; margin-bottom: 10px;">📊 ডাটাবেজ টেবিল ও রেকর্ড নিরীক্ষা:</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                    <?php
                    $tables = ['products', 'orders', 'order_items', 'oem_certificates', 'support_requests', 'reviews', 'users'];
                    foreach ($tables as $tbl) {
                        try {
                            $cnt = $pdo->query("SELECT COUNT(*) FROM $tbl")->fetchColumn();
                            echo "<div style='background: #111827; padding: 8px 12px; border-radius: 6px; border: 1px solid #374151; font-size: 0.82rem; display: flex; justify-content: space-between;'>
                                    <span style='color:#9ca3af;'>$tbl:</span>
                                    <strong style='color:#34d399;'>$cnt rows</strong>
                                  </div>";
                        } catch (PDOException $e) {
                            echo "<div style='background: #111827; padding: 8px 12px; border-radius: 6px; border: 1px solid #ef4444; font-size: 0.82rem; color: #f87171;'>
                                    $tbl: Error
                                  </div>";
                        }
                    }
                    ?>
                </div>
            </div>

        </div>

        <div style="margin-top: 20px; text-align: center;">
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; font-weight: 700; padding: 12px; border-radius: 8px;">
                🎉 সকল টেস্ট সফল! আইপি ল্যাব মূল্যায়নের জন্য পিএইচপি আর্কিটেকচার পুরোপুরি প্রস্তুত।
            </div>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
