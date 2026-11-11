<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: warranty_claim.php (Form Processing & Secure File Upload)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Lost My Buds
 * ==============================================================================================
 * 
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: ফাইল আপলোডের সময় ফর্মে enctype="multipart/form-data" কেন দিতে হয়?
 *    উত্তর: ডিফল্ট enctype হলো application/x-www-form-urlencoded যা শুধুমাত্র টেক্সট ডাটা পাঠাতে পারে।
 *          বাইনারি ডাটা (যেমন ইমেজ, ফাইল) পাঠাতে হলে ব্রাউজারকে নির্দেশ দিতে multipart/form-data আবশ্যক।
 * ২. প্রশ্ন: PRG (Post/Redirect/Get) প্যাটার্ন কী এবং কেন ব্যবহার করেছ?
 *    উত্তর: ফর্ম সাবমিট হওয়ার পর header("Location: ...") দিয়ে রিডাইরেক্ট করা হয় যাতে ইউজার ব্রাউজার
 *          রিলোড (F5) দিলেও একই ডাটা দ্বিতীয়বার ডাটাবেজে ইনসার্ট না হয়।
 * ৩. প্রশ্ন: ফাইল আপলোডের সিকিউরিটিতে কী কী চেক করা হয়েছে?
 *    উত্তর: (ক) ফাইলের এক্সটেনশন ও MIME টাইপ চেক (শুধুমাত্র jpg, png, webp অ্যালাউড), 
 *          (খ) সাইজ লিমিট (সর্বোচ্চ 2MB), 
 *          (গ) ফাইলের আসল নাম পরিবর্তন করে ইউনিক র‍্যান্ডম নাম দেওয়া যাতে স্ক্রিপ্ট ইঞ্জেকশন না ঘটে।
 * ==============================================================================================
 */

// ১. সেশন শুরু এবং পেজ টাইটেল
$page_title = "ওয়ারেন্টি ক্লেইম ও সাপোর্ট ডেস্ক";
require_once __DIR__ . '/config/db.php';

// ভ্যারিয়েবলসমূহ প্রারম্ভিক মান
$errors = [];
$name = '';
$phone = '';
$email = '';
$brand = '';
$model = '';
$missing_part = 'Left Earbud';
$serial = isset($_GET['serial']) ? trim($_GET['serial']) : '';
$message = '';

// ২. ফর্ম সাবমিশন হ্যান্ডলিং (শুধুমাত্র POST রিকোয়েস্টের জন্য)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ৩. ইনপুট সংগ্রহ ও স্যানিটাইজেশন
    $name = isset($_POST['name']) ? trim(htmlspecialchars($_POST['name'])) : '';
    $phone = isset($_POST['phone']) ? trim(htmlspecialchars($_POST['phone'])) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $brand = isset($_POST['brand']) ? trim(htmlspecialchars($_POST['brand'])) : '';
    $model = isset($_POST['model']) ? trim(htmlspecialchars($_POST['model'])) : '';
    $missing_part = isset($_POST['missing_part']) ? trim(htmlspecialchars($_POST['missing_part'])) : 'Left Earbud';
    $serial = isset($_POST['serial']) ? trim(htmlspecialchars($_POST['serial'])) : '';
    $message = isset($_POST['message']) ? trim(htmlspecialchars($_POST['message'])) : '';

    // ৪. ইনপুট ভ্যালিডেশন
    if (empty($name)) {
        $errors[] = "কাস্টমারের পুরো নাম আবশ্যক।";
    }

    if (empty($phone) || strlen($phone) < 11) {
        $errors[] = "সঠিক মোবাইল নম্বর প্রদান করুন (কমপক্ষে ১১ ডিজিট)।";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "ইমেইল অ্যাড্রেসের ফরম্যাট সঠিক নয়।";
    }

    if (empty($brand) || empty($model)) {
        $errors[] = "ইয়ারবাডের ব্র্যান্ড ও মডেল উল্লেখ করুন।";
    }

    if (empty($message)) {
        $errors[] = "সমস্যা বা দাবির বিস্তারিত বিবরণ লিখুন।";
    }

    // ৫. সিকিউর ফাইল আপলোড হ্যান্ডলিং ($_FILES)
    $uploaded_filename = null;
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['proof_image'];

        // এরর চেক
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "ফাইল আপলোডে ত্রুটি ঘটেছে (Error Code: " . $file['error'] . ")";
        } else {
            // ফাইলের আকার যাচাই (সর্বোচ্চ 2MB = 2 * 1024 * 1024 বাইট)
            if ($file['size'] > 2097152) {
                $errors[] = "ফাইলের আকার সর্বোচ্চ 2 MB হতে পারবে।";
            }

            // এক্সটেনশন ও MIME টাইপ ভ্যালিডেশন
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_exts)) {
                $errors[] = "শুধুমাত্র JPG, PNG অথবা WEBP ফরম্যাটের ছবি আপলোড করা যাবে।";
            }

            // কোনো এরর না থাকলে ফাইল সেভ করা
            if (empty($errors)) {
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // ইউনিক ও নিরাপদ নাম তৈরি (র‍্যান্ডম হেক্স স্ট্রিং)
                $new_filename = 'claim_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $uploaded_filename = $new_filename;
                } else {
                    $errors[] = "সার্ভারে ফাইল সংরক্ষণ করতে ব্যর্থ হয়েছে।";
                }
            }
        }
    }

    // ৬. কোনো ভ্যালিডেশন এরর না থাকলে ডাটাবেজে ইনসার্ট করা
    if (empty($errors)) {
        try {
            // পূর্ণাঙ্গ ম্যাসেজ ফরম্যাট তৈরি (সিরিয়াল ও আপলোড সহ)
            $full_notes = "সমস্যা: " . $message;
            if (!empty($serial)) {
                $full_notes .= " | সিরিয়াল নং: " . $serial;
            }
            if ($uploaded_filename) {
                $full_notes .= " | সংযুক্ত ছবি: " . $uploaded_filename;
            }

            // PDO Prepared Statement
            $stmt = $pdo->prepare("INSERT INTO support_requests (name, phone, email, brand, model, missing_part, message, status, created_at) 
                                   VALUES (:name, :phone, :email, :brand, :model, :part, :msg, 'Pending', datetime('now', 'localtime'))");

            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':brand', $brand, PDO::PARAM_STR);
            $stmt->bindValue(':model', $model, PDO::PARAM_STR);
            $stmt->bindValue(':part', $missing_part, PDO::PARAM_STR);
            $stmt->bindValue(':msg', $full_notes, PDO::PARAM_STR);

            $stmt->execute();

            // সর্বশেষ ইনসার্ট করা রেকর্ডের অটো-ইনক্রিমেন্ট আইডি
            $ticket_id = $pdo->lastInsertId();

            // সেশনে সাকসেস মেসেজ সেট করা
            $_SESSION['flash_success'] = "🎉 আপনার ওয়ারেন্টি ক্লেইম টিকেট সফলভাবে রেজিস্টার্ড হয়েছে! টিকেট নম্বর: #TK-" . str_pad($ticket_id, 4, '0', STR_PAD_LEFT);

            // PRG প্যাটার্ন: রিডাইরেক্ট করে ডুপ্লিকেট সাবমিশন রোধ
            header("Location: warranty_claim.php");
            exit;

        } catch (PDOException $e) {
            $errors[] = "ডাটাবেজে তথ্য সংরক্ষণে সমস্যা: " . $e->getMessage();
        }
    }
}

// হেডার ইনক্লুড করা
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px;">

    <!-- হেডার কার্ড -->
    <div class="card" style="border-top: 4px solid #10b981;">
        <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 700; font-size: 0.75rem; padding: 4px 10px; border-radius: 6px;">
            PHP Form Handling & File Upload
        </span>
        <h1 style="font-size: 1.8rem; font-weight: 800; margin-top: 10px; color: #ffffff;">
            📋 অফিশিয়াল ওয়ারেন্টি ক্লেইম ও সার্ভিস ডেস্ক
        </h1>
        <p style="color: #94a3b8; font-size: 0.95rem; margin-top: 6px;">
            আপনার হারিয়ে যাওয়া বা নষ্ট হয়ে যাওয়া ইয়ারবাডের ওয়ারেন্টি ক্লেইম করতে নিচের ফর্মটি পূরণ করুন।
        </p>

        <!-- ভ্যালিডেশন এরর প্রদর্শনী -->
        <?php if (!empty($errors)): ?>
            <div style="margin-top: 18px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 14px 18px;">
                <h4 style="color: #f87171; font-size: 0.95rem; margin-bottom: 6px;">⚠️ অনুগ্রহ করে নিচের ভুলগুলো সংশোধন করুন:</h4>
                <ul style="color: #fca5a5; font-size: 0.88rem; margin-left: 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- ফর্ম: POST মেথড ও ফাইল আপলোডের জন্য multipart/form-data -->
        <form action="warranty_claim.php" method="POST" enctype="multipart/form-data" style="margin-top: 24px; display: flex; flex-direction: column; gap: 18px;">

            <!-- কাস্টমার নাম ও মোবাইল নম্বর -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">
                        আপনার নাম <span style="color: #f87171;">*</span>
                    </label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required
                           placeholder="যেমন: তানভীর আহমেদ"
                           style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">
                        মোবাইল নম্বর <span style="color: #f87171;">*</span>
                    </label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required
                           placeholder="০১৭xxxxxxxx"
                           style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem;">
                </div>
            </div>

            <!-- ইমেইল ও সিরিয়াল নম্বর -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">
                        ইমেইল অ্যাড্রেস
                    </label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                           placeholder="example@gmail.com"
                           style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">
                        OEM সিরিয়াল নম্বর (যদি থাকে)
                    </label>
                    <input type="text" name="serial" value="<?php echo htmlspecialchars($serial); ?>"
                           placeholder="যেমন: BR-OEM-APP-SN84001"
                           style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem; font-family: monospace;">
                </div>
            </div>

            <!-- ব্র্যান্ড ও মডেল সিলেকশন -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">
                        ইয়ারবাডের ব্র্যান্ড <span style="color: #f87171;">*</span>
                    </label>
                    <select name="brand" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem;">
                        <option value="">ব্র্যান্ড নির্বাচন করুন</option>
                        <option value="Apple" <?php echo ($brand === 'Apple') ? 'selected' : ''; ?>>Apple (AirPods)</option>
                        <option value="Samsung" <?php echo ($brand === 'Samsung') ? 'selected' : ''; ?>>Samsung (Galaxy Buds)</option>
                        <option value="Sony" <?php echo ($brand === 'Sony') ? 'selected' : ''; ?>>Sony (WF Series)</option>
                        <option value="Nothing" <?php echo ($brand === 'Nothing') ? 'selected' : ''; ?>>Nothing (Ear)</option>
                        <option value="OnePlus" <?php echo ($brand === 'OnePlus') ? 'selected' : ''; ?>>OnePlus Buds</option>
                        <option value="Google" <?php echo ($brand === 'Google') ? 'selected' : ''; ?>>Google Pixel Buds</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">
                        নির্দিষ্ট মডেল <span style="color: #f87171;">*</span>
                    </label>
                    <input type="text" name="model" value="<?php echo htmlspecialchars($model); ?>" required
                           placeholder="যেমন: AirPods Pro 2 / Buds 2 Pro"
                           style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem;">
                </div>
            </div>

            <!-- কোন অংশটি প্রয়োজন -->
            <div>
                <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                    দাবীকৃত অংশ (Claimed Part) <span style="color: #f87171;">*</span>
                </label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px;">
                    <label style="background: #030712; border: 1px solid #334155; padding: 10px 14px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="radio" name="missing_part" value="Left Earbud" <?php echo ($missing_part === 'Left Earbud') ? 'checked' : ''; ?>>
                        <span>বাম ইয়ারবাড (Left)</span>
                    </label>
                    <label style="background: #030712; border: 1px solid #334155; padding: 10px 14px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="radio" name="missing_part" value="Right Earbud" <?php echo ($missing_part === 'Right Earbud') ? 'checked' : ''; ?>>
                        <span>ডান ইয়ারবাড (Right)</span>
                    </label>
                    <label style="background: #030712; border: 1px solid #334155; padding: 10px 14px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="radio" name="missing_part" value="Charging Case" <?php echo ($missing_part === 'Charging Case') ? 'checked' : ''; ?>>
                        <span>চার্জিং কেস (Case)</span>
                    </label>
                </div>
            </div>

            <!-- সমস্যার বিবরণ -->
            <div>
                <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">
                    সমস্যার বিবরণ <span style="color: #f87171;">*</span>
                </label>
                <textarea name="message" rows="4" required
                          placeholder="আপনার ইয়ারবাডের কী সমস্যা হয়েছে তা বিস্তারিত লিখুন..."
                          style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #030712; color: #fff; font-size: 0.95rem; resize: vertical;"><?php echo htmlspecialchars($message); ?></textarea>
            </div>

            <!-- প্রমাণক ছবি আপলোড ($_FILES) -->
            <div style="background: rgba(14, 165, 233, 0.05); border: 1px dashed rgba(14, 165, 233, 0.3); padding: 16px; border-radius: 8px;">
                <label style="display: block; font-size: 0.88rem; font-weight: 600; color: #38bdf8; margin-bottom: 6px;">
                    📷 প্রমাণক ছবি বা ক্রয়ের রসিদ আপলোড করুন (ঐচ্ছিক)
                </label>
                <input type="file" name="proof_image" accept="image/png, image/jpeg, image/webp"
                       style="font-size: 0.88rem; color: #94a3b8;">
                <p style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">
                    সর্বোচ্চ সাইজ: 2MB | সমর্থিত ফরম্যাট: JPG, PNG, WEBP (PHP $_FILES প্রসেসিং ডেমো)
                </p>
            </div>

            <!-- সাবমিট বাটন -->
            <button type="submit" 
                    style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 14px 24px; border-radius: 10px; font-weight: 700; font-size: 1.05rem; cursor: pointer; transition: all 0.2s ease; margin-top: 8px;">
                📤 ওয়ারেন্টি ক্লেইম সাবমিট করুন (PHP POST)
            </button>
        </form>
    </div>

    <!-- ভাইভা টিপস -->
    <div class="card" style="background: #030712; border: 1px dashed #334155;">
        <h4 style="color: #38bdf8; font-size: 0.92rem; margin-bottom: 8px;">
            🎓 ভাইভা প্রশ্ন: ফাইল আপলোডের সময় পিএইচপির কী কী চেক নিশ্চিত করা হয়েছে?
        </h4>
        <p style="color: #94a3b8; font-size: 0.85rem; line-height: 1.6;">
            ১. <code>$_FILES['proof_image']['error'] === UPLOAD_ERR_OK</code> দিয়ে ট্রান্সমিশন চেক করা হয়েছে।<br>
            ২. <code>$_FILES['proof_image']['size'] &lt;= 2097152</code> দিয়ে সাইজ লিমিটেশন (2MB) চেক করা হয়েছে।<br>
            ৩. <code>pathinfo($file['name'], PATHINFO_EXTENSION)</code> দিয়ে ক্ষতিকারক .php বা .exe ফাইল আপলোড ব্লক করা হয়েছে।<br>
            ৪. <code>move_uploaded_file()</code> ব্যবহার করে নিরাপদ ডিরেক্টরিতে রিনেম করে সংরক্ষণ করা হয়েছে।
        </p>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
