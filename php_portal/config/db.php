<?php
/**
 * ==============================================================================================
 * ফাইল পরিচিতি: db.php (Database Connection Configuration)
 * কোর্স: Internet Programming Lab (IP Lab) / Web Engineering
 * প্রজেক্ট: BudsRecover — Lost My Buds
 * ==============================================================================================
 *
 * 🎓 ম্যামের সম্ভাব্য প্রশ্ন ও উত্তর (Viva Q&A):
 * ১. প্রশ্ন: ডাটাবেজ কানেকশনের জন্য mysqli না ব্যবহার করে PDO কেন ব্যবহার করেছ?
 *    উত্তর: PDO (PHP Data Objects) আধুনিক, নিরাপদ এবং মাল্টি-ডাটাবেজ সাপোর্ট করে (MySQL, SQLite, PostgreSQL ইত্যাদি)।
 *          তাছাড়া PDO তে Prepared Statements বিল্ট-ইন থাকে, যা SQL Injection আক্রমণ পুরোপুরি রোধ করে।
 * ২. প্রশ্ন: try-catch ব্লক কেন দিয়েছ?
 *    উত্তর: ডাটাবেজ কানেকশনে কোনো সমস্যা হলে যেন স্ক্রিপ্ট ক্র্যাশ না করে এবং ইউজারকে কাস্টম নিরাপদ মেসেজ দেখানো যায়।
 * ==============================================================================================
 */

// ১. এরর রিপোর্টিং চালু রাখা (ডেভেলপমেন্ট ও ল্যাব টেস্টিং এর সুবিধার্থে)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ২. ডাটাবেজ ফাইলের পাথ নির্ধারণ
// প্রজেক্টের রুট ডিরেক্টরিতে থাকা 'lostmybuds.db' ফাইলটিকে স্বয়ংক্রিয়ভাবে শনাক্ত করার লজিক:
$possible_db_paths = [
    __DIR__ . '/../../lostmybuds.db',                                           // রিলেটিভ পাথ (লোকাল টেস্ট বা প্রোজেক্ট ফোল্ডারে থাকলে)
    'C:/Users/Administrator/Desktop/LOST MY BUDS PROJECT/lostmybuds.db',        // ফিক্সড অ্যাবসলিউট পাথ
    dirname(__DIR__, 2) . '/lostmybuds.db'                                      // প্যারেন্ট ডিরেক্টরি পাথ
];

$db_file = null;
foreach ($possible_db_paths as $path) {
    if (file_exists($path)) {
        $db_file = realpath($path);
        break;
    }
}

// ডাটাবেজ ফাইল খুঁজে না পেলে ইউজারকে সুস্পষ্ট ডিরেকশন দেওয়া
if (!$db_file) {
    die("❌ ডাটাবেজ ফাইল পাওয়া যায়নি! অনুগ্রহ করে নিশ্চিত করুন 'lostmybuds.db' মূল ডিরেক্টরিতে বিদ্যমান আছে।");
}

try {
    // ৩. PDO (PHP Data Objects) এর মাধ্যমে SQLite3 ডাটাবেজের সাথে সংযোগ স্থাপন
    // DSN (Data Source Name) তৈরি: "sqlite:ফাইলের_পাথ"
    $dsn = "sqlite:" . $db_file;
    
    // PDO ইনস্ট্যান্স তৈরি
    $pdo = new PDO($dsn);

    // ৪. PDO এরর মোড সেট করা (EXCEPTION মোড যাতে কোনো ভুল কোয়েরি হলে সাথে সাথে ক্যাচ করা যায়)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ৫. ডাটা ফেচিং ডিফল্ট মোড: অ্যাসোসিয়েটিভ অ্যারে (FETCH_ASSOC)
    // এর ফলে $row['column_name'] দিয়ে সরাসরি ডাটা রিড করা যাবে
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ৬. ডাটাবেজ পারফরম্যান্স টিউনিং (WAL মোড ও দ্রুত লেখার জন্য)
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA foreign_keys = ON;");

} catch (PDOException $e) {
    // কানেকশন ফেইল হলে এরর মেসেজ হ্যান্ডেল করা
    die("<div style='background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; font-family:sans-serif;'>
            <h3>⚠️ ডাটাবেজ কানেকশন ব্যর্থ হয়েছে!</h3>
            <p><strong>ত্রুটি:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
         </div>");
}

// এই $pdo অবজেক্টটি এখন অন্য যেকোনো স্ক্রিপ্টে require_once করে কোয়েরি করার জন্য প্রস্তুত
?>
