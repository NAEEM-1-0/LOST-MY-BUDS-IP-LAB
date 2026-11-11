<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: verify_serial.php (OEM Serial & Authenticity Verifier)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Lost My Buds
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: কোডে SQL Injection কিভাবে প্রতিরোধ করেছ?
 *    উত্তর: আমরা PDO Prepared Statement ($pdo->prepare()) এবং প্যারামিটার বাইন্ডিং (:serial) 
 *          ব্যবহার করেছি। ফলে ইউজারের ইনপুট সরাসরি SQL স্ট্রিং হিসেবে এক্সিকিউট হয় না, বরং 
 *          প্যারামিটার হিসেবে পাস হয়। এটি SQL Injection আক্রমণ সম্পূর্ণভাবে আটকে দেয়।
 * ২. প্রশ্ন: GET এবং POST এর মধ্যে পার্থক্য কী এবং এখানে কোনটা কেন নিয়েছ?
 *    উত্তর: ইউজার যখন ফর্ম সাবমিট করে তখন POST রিকোয়েস্ট যায়। কিন্তু URL এ সরাসরি লিংকের মাধ্যমে 
 *          ভেরিফাই করার জন্য আমরা GET ও সাপোর্ট রেখেছি ($_REQUEST বা $_GET/$_POST চেক করে)।
 * ==============================================================================================
 */

// ১. পেজের নাম ও ডাটাবেজ কানেকশন লোড
$page_title = "OEM সিরিয়াল ও সত্যতা ভেরিফিকেশন";
require_once __DIR__ . '/config/db.php';

// ২. ইনপুট সংগ্রহ ও স্যানিটাইজেশন (Sanitization)
// GET বা POST যেকোনো মাধ্যমেই ইউজার সার্চ করতে পারে
$serial_input = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ফর্ম সাবমিট হলে $_POST থেকে ইনপুট নেওয়া
    $serial_input = isset($_POST['serial_query']) ? trim($_POST['serial_query']) : '';
} elseif (isset($_GET['serial'])) {
    // ইউআরএল প্যারামিটার হিসেবে আসলে $_GET থেকে ইনপুট নেওয়া
    $serial_input = trim($_GET['serial']);
}

// ইনপুট থেকে ক্ষতিকারক স্ক্রিপ্ট বা HTML ট্যাগ ফিল্টার করা (XSS প্রতিরোধ)
$serial_clean = htmlspecialchars($serial_input, ENT_QUOTES, 'UTF-8');

$certificate = null;
$searched = false;
$error_msg = '';

// ৩. ডাটাবেজে অনুসন্ধান পরিচালনা করা
if (!empty($serial_clean)) {
    $searched = true;
    try {
        // ৪. PDO Prepared Statement ব্যবহার (SQL Injection রোধ করতে)
        $sql = "SELECT c.*, p.price, p.image, p.description 
                FROM oem_certificates c
                LEFT JOIN products p ON c.product_id = p.id
                WHERE UPPER(c.serial_number) = UPPER(:query1) 
                   OR UPPER(c.certificate_code) = UPPER(:query2)
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        
        // ৫. প্যারামিটার বাইন্ড করা
        $stmt->bindValue(':query1', $serial_clean, PDO::PARAM_STR);
        $stmt->bindValue(':query2', $serial_clean, PDO::PARAM_STR);
        
        // ৬. কুয়েরি এক্সিকিউট করা
        $stmt->execute();
        
        // রেজাল্ট ফেচ করা
        $certificate = $stmt->fetch();

    } catch (PDOException $e) {
        $error_msg = "ডাটাবেজ কুয়েরি করতে সমস্যা: " . $e->getMessage();
    }
}

// হেডার ইনক্লুড করা
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- হেডিং কার্ড -->
    <div class="card" style="text-align: center; border-top: 4px solid #38bdf8;">
        <span style="font-size: 2.2rem;">🛡️</span>
        <h1 style="font-size: 1.8rem; font-weight: 800; margin-top: 8px; color: #ffffff;">
            OEM সিরিয়াল ও অরিজিনালিটি ভেরিফিকেশন ইঞ্জিন
        </h1>
        <p style="color: #94a3b8; font-size: 0.95rem; margin-top: 6px;">
            পিএইচপি পিডিও (PDO) প্রিপেয়ার্ড স্টেটমেন্ট দ্বারা পরিচালিত নিরাপদ সিরিয়াল চেকিং সিস্টেম
        </p>

        <!-- সার্চ ফর্ম (POST মেথড ব্যবহার করে ডাটা পাঠানো) -->
        <form action="verify_serial.php" method="POST" style="margin-top: 24px; display: flex; gap: 10px; max-width: 650px; margin-left: auto; margin-right: auto; flex-wrap: wrap;">
            <input type="text" name="serial_query" value="<?php echo htmlspecialchars($serial_clean); ?>" 
                   placeholder="যেমন: BR-OEM-APP-SN84001 অথবা OEM-CERT-APP-01" 
                   required
                   style="flex: 1; min-width: 280px; padding: 14px 18px; border-radius: 10px; border: 1px solid #334155; background: #030712; color: #ffffff; font-size: 1rem; font-family: 'JetBrains Mono', monospace;">
            <button type="submit" 
                    style="background: linear-gradient(135deg, #0284c7, #2563eb); color: white; border: none; padding: 14px 24px; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.2s ease;">
                🔍 যাচাই করুন (POST)
            </button>
        </form>

        <!-- কুইক টেস্ট বাটনসমূহ (ল্যাব পরীক্ষার সুবিধার্থে ১-ক্লিকে টেস্ট) -->
        <div style="margin-top: 18px; display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 0.8rem; color: #64748b;">ল্যাব টেস্টের ডেমো সিরিয়াল:</span>
            <a href="verify_serial.php?serial=BR-OEM-APP-SN84001" style="font-size: 0.78rem; background: #1e293b; color: #38bdf8; padding: 4px 10px; border-radius: 6px; text-decoration: none; border: 1px solid #334155;">Apple Pro 2 (#84001)</a>
            <a href="verify_serial.php?serial=BR-OEM-SAM-SN84007" style="font-size: 0.78rem; background: #1e293b; color: #38bdf8; padding: 4px 10px; border-radius: 6px; text-decoration: none; border: 1px solid #334155;">Samsung (#84007)</a>
            <a href="verify_serial.php?serial=FAKE-CLONE-999" style="font-size: 0.78rem; background: #1e293b; color: #f87171; padding: 4px 10px; border-radius: 6px; text-decoration: none; border: 1px solid #334155;">ভুয়া ক্লোন টেস্ট</a>
        </div>
    </div>

    <!-- ফলাফল সেকশন -->
    <?php if ($searched): ?>
        <?php if ($certificate): ?>
            <!-- সফল ভেরিফিকেশন কার্ড -->
            <div class="card" style="border: 2px solid #10b981; background: #061e1b; position: relative;">
                <div style="position: absolute; top: 16px; right: 16px; background: #10b981; color: #022c22; font-weight: 800; font-size: 0.75rem; padding: 4px 12px; border-radius: 20px; text-transform: uppercase;">
                    ✓ ১০০% জেনুইন OEM সার্টিফাইড
                </div>

                <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                    <div style="font-size: 3rem;">🏅</div>
                    <div style="flex: 1;">
                        <h2 style="font-size: 1.4rem; font-weight: 800; color: #34d399;">
                            আসল OEM হার্ডওয়্যার সার্টিফিকেট পাওয়া গেছে!
                        </h2>
                        <p style="color: #a7f3d0; font-size: 0.9rem; margin-top: 4px;">
                            সিরিয়াল নাম্বার: <strong style="font-family: monospace; font-size: 1rem; color: #ffffff;"><?php echo htmlspecialchars($certificate['serial_number']); ?></strong>
                        </p>
                    </div>
                </div>

                <!-- সার্টিফিকেট স্পেসিফিকেশন গ্রিড -->
                <div style="margin-top: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; background: rgba(0, 0, 0, 0.25); padding: 18px; border-radius: 10px;">
                    <div>
                        <div style="font-size: 0.75rem; color: #6ee7b7;">সার্টিফিকেট কোড</div>
                        <div style="font-weight: 700; color: #ffffff; font-family: monospace;"><?php echo htmlspecialchars($certificate['certificate_code']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #6ee7b7;">ব্র্যান্ড ও মডেল</div>
                        <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($certificate['brand'] . ' ' . $certificate['model']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #6ee7b7;">যন্ত্রাংশের ধরণ (Part)</div>
                        <div style="font-weight: 700; color: #38bdf8;"><?php echo htmlspecialchars($certificate['part_type']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #6ee7b7;">ব্যাচ নম্বর</div>
                        <div style="font-weight: 700; color: #ffffff; font-family: monospace;"><?php echo htmlspecialchars($certificate['batch_number']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #6ee7b7;">ল্যাব অ্যাকোস্টিক টেস্ট</div>
                        <div style="font-weight: 700; color: #34d399;"><?php echo htmlspecialchars($certificate['lab_test_status']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #6ee7b7;">ANC সক্রিয়তা রেট</div>
                        <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($certificate['anc_efficiency']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #6ee7b7;">ফ্রিকোয়েন্সি রেসপন্স</div>
                        <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($certificate['frequency_response']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: #6ee7b7;">অফিসিয়াল রিপ্লেসমেন্ট ওয়ারেন্টি</div>
                        <div style="font-weight: 700; color: #f59e0b;"><?php echo htmlspecialchars($certificate['warranty_days']); ?> দিন রিপ্লেসমেন্ট</div>
                    </div>
                </div>

                <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid rgba(16, 185, 129, 0.2); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div style="font-size: 0.8rem; color: #6ee7b7;">
                        পরীক্ষক ল্যাব: <strong><?php echo htmlspecialchars($certificate['tested_by']); ?></strong>
                    </div>
                    <a href="warranty_claim.php?serial=<?php echo urlencode($certificate['serial_number']); ?>" 
                       style="background: #10b981; color: #022c22; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border-radius: 8px; text-decoration: none;">
                        📋 এই সিরিয়ালের ওয়ারেন্টি ক্লেইম করুন &rarr;
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- খুঁজে না পেলে বা ফেক সিরিয়াল হলে সতর্কবার্তা -->
            <div class="card" style="border: 2px solid #ef4444; background: #200d0d; text-align: center;">
                <span style="font-size: 3rem;">🚫</span>
                <h2 style="font-size: 1.4rem; font-weight: 800; color: #f87171; margin-top: 8px;">
                    সতর্কতা: কোনো বৈধ OEM রেকর্ড পাওয়া যায়নি!
                </h2>
                <p style="color: #fca5a5; font-size: 0.95rem; margin-top: 6px; max-width: 600px; margin-left: auto; margin-right: auto;">
                    ইনপুটকৃত সিরিয়াল বা কোড "<strong><?php echo htmlspecialchars($serial_clean); ?></strong>" আমাদের ডাটাবেজে রেজিস্টার্ড নয়। 
                    এটি কোনো ক্লোন বা নকল ইয়ারবাড হতে পারে, অথবা ভুল সিরিয়াল টাইপ করা হয়েছে।
                </p>
                <div style="margin-top: 16px;">
                    <a href="verify_serial.php" style="color: #38bdf8; text-decoration: underline; font-size: 0.9rem;">আবার চেষ্টা করুন</a>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ভাইভা ব্যাখ্যা বক্স -->
    <div class="card" style="background: #030712; border: 1px dashed #334155;">
        <h4 style="color: #f59e0b; font-size: 0.95rem; margin-bottom: 8px;">
            🎓 ভাইভা ব্যাখ্যা: Prepared Statement কেন ব্যবহার করা হয়েছে?
        </h4>
        <div class="code-box">
// পিএইচপি কোডের অংশ:<br>
$stmt = $pdo->prepare("SELECT * FROM oem_certificates WHERE serial_number = :query");<br>
$stmt->bindValue(':query', $serial_clean, PDO::PARAM_STR);<br>
$stmt->execute();
        </div>
        <p style="color: #94a3b8; font-size: 0.82rem; margin-top: 8px;">
            ম্যামকে বলবেন: "ম্যাম, সরাসরি SQL এর ভেতর স্ট্রিং কনক্যাটেনেশন (যেমন: <code>... WHERE serial = '$input'</code>) করলে ইউজার <code>' OR '1'='1</code> দিয়ে পুরো ডাটাবেজ হ্যাক করতে পারত। 
            আমরা <code>prepare()</code> এবং <code>bindValue()</code> ব্যবহার করায় ডাটাবেজ ইঞ্জিন ইনপুটকে কেবল বিশুদ্ধ ডাটা হিসেবে ট্রিট করে, এক্সিকিউটেবল কোড হিসেবে নয়।"
        </p>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
