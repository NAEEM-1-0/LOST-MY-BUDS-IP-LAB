"""
=============================================================================
প্রজেক্ট নাম: BudsRecover (ইয়ারবাড রিপ্লেসমেন্ট ই-কমার্স প্ল্যাটফর্ম)
ফাইল: database.py
বিবরণ: SQLite ডাটাবেজ তৈরি, টেবিল স্কিমা এবং প্রাথমিক ডেমো প্রোডাক্ট ডাটা সিডিং।
গ্রুপ প্রজেক্টের সুবিধার জন্য প্রতিটি লাইনে বাংলা কমেন্ট যুক্ত করা হয়েছে।
=============================================================================
"""

import sqlite3  # পাইথনের বিল্ট-ইন SQLite ডাটাবেজ হ্যান্ডলার মডিউল
import os       # ফাইল ও ডিরেক্টরি পাথ নিয়ন্ত্রণের জন্য os মডিউল
from werkzeug.security import generate_password_hash  # পাসওয়ার্ড সুরক্ষিতভাবে হ্যাশ করার জন্য

# ডাটাবেজ ফাইলের সম্পূর্ণ পাথ নির্ধারণ করা (এই পাইথন ফাইলের একই ডিরেক্টরিতে lostmybuds.db তৈরি হবে)
DB_PATH = os.path.join(os.path.dirname(__file__), 'lostmybuds.db')

def get_db():
    """
    ডাটাবেজের সাথে সংযোগ (Connection) স্থাপন করার ফাংশন।
    row_factory = sqlite3.Row ব্যবহারের ফলে ডাটাবেজের প্রতিটি সারিকে ডিকশনারির (dict) মত কি (key) দিয়ে অ্যাক্সেস করা যায়।
    """
    conn = sqlite3.connect(DB_PATH)  # ডাটাবেজ ফাইলের সাথে সংযোগ খোলা
    conn.row_factory = sqlite3.Row   # কুয়েরির ফলাফল যেন কলামের নাম দিয়ে পড়া যায় (যেমন row['name'])
    return conn                      # কানেকশন অবজেক্ট রিটার্ন করা

def init_db():
    """
    ডাটাবেজ টেবিলগুলো তৈরি করা এবং প্রাথমিক ডেমো ডাটা (Products & Admin User) ইনসার্ট করার প্রধান ফাংশন।
    সার্ভার চালু হওয়ার সময় এটি একবার কল হয়।
    """
    conn = get_db()        # কানেকশন নেওয়া
    c = conn.cursor()      # এসকিউএল কুয়েরি এক্সিকিউট করার জন্য কার্সার তৈরি

    # ১. ইউজার টেবিল (গ্রাহক ও এডমিনদের তথ্য সংরক্ষণের জন্য)
    c.execute("""
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,           -- প্রতিটি ইউজারের ইউনিক সিরিয়াল আইডি
        name TEXT NOT NULL,                             -- ইউজারের পুরো নাম
        email TEXT UNIQUE NOT NULL,                     -- লগইন ইমেইল (অবশ্যই ইউনিক হতে হবে)
        password TEXT NOT NULL,                         -- হ্যাশ করা নিরাপদ পাসওয়ার্ড
        phone TEXT,                                     -- কন্টাক্ট ফোন নম্বর
        address TEXT,                                   -- ডিফল্ট ডেলিভারি ঠিকানা
        is_admin INTEGER DEFAULT 0,                     -- ১ হলে এডমিন, ০ হলে সাধারণ কাস্টমার
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- অ্যাকাউন্ট তৈরির সময়
    )
    """)

    # ২. প্রোডাক্টস টেবিল (রিপ্লেসমেন্ট ইয়ারবাড ও চার্জিং কেসের তালিকা)
    c.execute("""
    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,           -- প্রোডাক্টের ইউনিক আইডি
        name TEXT NOT NULL,                             -- প্রোডাক্টের পুরো নাম
        brand TEXT NOT NULL,                            -- ব্র্যান্ড নাম (Apple, Samsung, Sony, Nothing, ইত্যাদি)
        model TEXT NOT NULL,                            -- মডেল নাম (AirPods Pro 2, Galaxy Buds 2 Pro, ইত্যাদি)
        category TEXT NOT NULL,                         -- পার্ট টাইপ (Left Bud, Right Bud, Charging Case, Accessories)
        price REAL NOT NULL,                            -- বিক্রয় মূল্য (টাকায়)
        original_price REAL,                            -- রেগুলার মূল্য (ডিসকাউন্ট দেখানোর জন্য)
        image TEXT,                                     -- ছবির লিঙ্ক বা ফাইলের নাম
        description TEXT,                               -- বিস্তারিত বিবরণ ও ফিচার
        condition TEXT DEFAULT 'Original OEM',          -- কন্ডিশন (Original Brand New, Certified Refurbished)
        compatibility TEXT,                             -- কোন কোন মডেলের সাথে ম্যাচ করবে (মডেল কোড)
        pairing_guide TEXT,                             -- নতুন বাডটি পুরনো সেটের সাথে পেয়ার করার নির্দেশিকা
        stock INTEGER DEFAULT 10,                       -- স্টকে থাকা পার্টসের সংখ্যা
        rating REAL DEFAULT 4.9,                        -- কাস্টমার রেটিং (৫ এর মধ্যে)
        reviews_count INTEGER DEFAULT 18,               -- মোট রিভিউ সংখ্যা
        is_featured INTEGER DEFAULT 0,                  -- হোমপেজে হাইলাইট করা হবে কি না (১ বা ০)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- প্রোডাক্ট আপলোডের সময়
    )
    """)

    # ৩. অর্ডার টেবিল (গ্রাহকদের কেনাকাটার অর্ডার সংরক্ষণ)
    c.execute("""
    CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,           -- অর্ডারের ইউনিক ডাটাবেজ আইডি
        order_code TEXT UNIQUE,                         -- কাস্টমারকে দেখানোর ইউনিক ইনভয়েস কোড (যেমন BR-8291)
        user_id INTEGER NOT NULL,                       -- কোন ইউজার অর্ডার করেছে তার আইডি
        total REAL NOT NULL,                            -- সর্বমোট টাকার পরিমাণ
        delivery_fee REAL DEFAULT 60,                   -- ডেলিভারি চার্জ
        payment_method TEXT DEFAULT 'Cash on Delivery', -- পেমেন্ট মেথড (Cash on Delivery, bKash, Nagad)
        payment_status TEXT DEFAULT 'Unpaid',           -- পেমেন্ট স্ট্যাটাস (Paid বা Unpaid)
        customer_name TEXT NOT NULL,                    -- পার্সেল রিসিভ করবেন যার নাম
        customer_phone TEXT NOT NULL,                   -- যোগাযোগের ফোন নম্বর
        customer_email TEXT,                            -- ইমেইল (অপশনাল)
        shipping_address TEXT NOT NULL,                 -- পূর্ণাঙ্গ ডেলিভারি ঠিকানা
        city TEXT NOT NULL,                             -- শহর/জেলা (Dhaka বা Outside Dhaka)
        order_notes TEXT,                               -- অতিরিক্ত ডেলিভারি নোট
        status TEXT DEFAULT 'Pending',                  -- অর্ডারের অবস্থা (Pending, Processing, Shipped, Delivered)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- অর্ডার প্লেস করার সময়
    )
    """)

    # ৪. অর্ডার আইটেমস টেবিল (একটি অর্ডারে কোন কোন প্রোডাক্ট কত পিস ছিল)
    c.execute("""
    CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,           -- আইটেম এন্ট্রি আইডি
        order_id INTEGER NOT NULL,                      -- কোন অর্ডারের অংশ (orders টেবিলের id)
        product_id INTEGER NOT NULL,                    -- কোন প্রোডাক্ট (products টেবিলের id)
        product_name TEXT NOT NULL,                     -- অর্ডার করার সময়ের প্রোডাক্টের নাম
        category TEXT NOT NULL,                         -- পার্ট ক্যাটাগরি (Left Bud, Right Bud, ইত্যাদি)
        price REAL NOT NULL,                            -- অর্ডার করার সময়ের প্রতি পিসের দাম
        quantity INTEGER NOT NULL,                      -- কত পিস অর্ডার করা হয়েছে
        image TEXT                                      -- প্রোডাক্টের ছবির লিঙ্ক
    )
    """)

    # ৫. কাস্টমার সাপোর্ট / পার্ট ম্যাচিং রিকোয়েস্ট টেবিল
    c.execute("""
    CREATE TABLE IF NOT EXISTS support_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,           -- রিকোয়েস্ট আইডি
        name TEXT NOT NULL,                             -- গ্রাহকের নাম
        phone TEXT NOT NULL,                            -- যোগাযোগের ফোন নম্বর
        email TEXT,                                     -- গ্রাহকের ইমেইল
        brand TEXT NOT NULL,                            -- ইয়ারবাডের ব্র্যান্ড
        model TEXT NOT NULL,                            -- মডেল নাম
        missing_part TEXT NOT NULL,                     -- কোন অংশ হারিয়েছে (Left Bud / Right Bud / Case)
        message TEXT NOT NULL,                          -- বিস্তারিত সমস্যা
        status TEXT NOT NULL DEFAULT 'Pending',         -- সমাধান স্ট্যাটাস (Pending / In Progress / Resolved)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- রিকোয়েস্ট করার সময়
    )
    """)

    # ৬. কাস্টমার রিভিউ ও রেটিং টেবিল
    c.execute("""
    CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,           -- রিভিউ আইডি
        product_id INTEGER NOT NULL,                    -- কোন প্রোডাক্টের রিভিউ
        user_name TEXT NOT NULL,                        -- রিভিউ প্রদানকারীর নাম
        rating INTEGER NOT NULL,                        -- ১ থেকে ৫ স্টার
        comment TEXT NOT NULL,                          -- রিভিউ কমেন্ট
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- রিভিউ এর সময়
    )
    """)

    # ৭. ট্রেড-ইন / পড়ে থাকা বাড বা কেস বিক্রি করার রিকোয়েস্ট টেবিল (Trade-In / Buyback)
    c.execute("""
    CREATE TABLE IF NOT EXISTS trade_in_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,           -- রিকোয়েস্ট আইডি
        name TEXT NOT NULL,                             -- বিক্রেতার নাম
        phone TEXT NOT NULL,                            -- যোগাযোগের ফোন নম্বর
        brand TEXT NOT NULL,                            -- ব্র্যান্ড নাম
        model TEXT NOT NULL,                            -- মডেল নাম
        part_type TEXT NOT NULL,                        -- কোন পার্টটি বিক্রি করতে চান (Left/Right/Case)
        condition TEXT NOT NULL,                        -- পণ্যের বর্তমান অবস্থা (Mint / Good / Fair)
        expected_price REAL,                            -- কাঙ্ক্ষিত বিক্রয় মূল্য
        status TEXT DEFAULT 'Pending',                  -- রিকোয়েস্ট স্ট্যাটাস (Pending / Approved / Completed)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- আবেদন করার সময়
    )
    """)

    # ৮. জেনুইন OEM সার্টিফিকেট ও QR কোড ভেরিফিকেশন টেবিল (OEM Certificates Table)
    c.execute("""
    CREATE TABLE IF NOT EXISTS oem_certificates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,           -- সার্টিফিকেট আইডি
        certificate_code TEXT UNIQUE NOT NULL,          -- ইউনিক কিউআর কোড টোকেন (যেমন OEM-CERT-APL-01)
        product_id INTEGER,                             -- সংশ্লিষ্ট প্রোডাক্ট আইডি
        brand TEXT NOT NULL,                            -- ব্র্যান্ড নাম
        model TEXT NOT NULL,                            -- মডেল নাম
        part_type TEXT NOT NULL,                        -- পার্ট টাইপ (Left Bud / Right Bud / Charging Case)
        serial_number TEXT UNIQUE NOT NULL,             -- পণ্যের হার্ডওয়্যার সিরিয়াল কোড
        batch_number TEXT NOT NULL,                     -- ম্যানুফ্যাকচারিং ব্যাচ কোড
        lab_test_status TEXT DEFAULT 'Passed (১০০% আসল OEM জেনুইন)', -- ল্যাব টেস্টিং রিপোর্ট
        tested_by TEXT DEFAULT 'BudsRecover Acoustic & Hardware Lab (Dhaka)', -- পরীক্ষাকারী ল্যাব
        frequency_response TEXT DEFAULT '20Hz - 20,000Hz (OEM Spec Matched)', -- অডিও ফ্রিকোয়েন্সি রেসপন্স
        anc_efficiency TEXT DEFAULT '99.1% Active Noise Cancellation Effective', -- নয়েজ ক্যান্সেলেশন কার্যকারিতা
        warranty_days INTEGER DEFAULT 90,               -- ওয়ারেন্টির মেয়াদ (৯০ দিন)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- সার্টিফিকেট তৈরির সময়
    )
    """)

    # products টেবিলে সিরিয়াল নম্বর ও সার্টিফিকেট কোড কলাম চেক করে যোগ করা (Data Migration)
    prod_cols = [r[1] for r in c.execute("PRAGMA table_info(products)").fetchall()]
    if 'serial_number' not in prod_cols:
        c.execute("ALTER TABLE products ADD COLUMN serial_number TEXT")
    if 'certificate_code' not in prod_cols:
        c.execute("ALTER TABLE products ADD COLUMN certificate_code TEXT")

    # orders টেবিলে কুপন কোড ও ডিসকাউন্ট কলাম চেক করে যোগ করা (Data Migration)
    order_cols = [r[1] for r in c.execute("PRAGMA table_info(orders)").fetchall()]
    if 'coupon_code' not in order_cols:
        c.execute("ALTER TABLE orders ADD COLUMN coupon_code TEXT")
    if 'discount' not in order_cols:
        c.execute("ALTER TABLE orders ADD COLUMN discount REAL DEFAULT 0")

    # প্রাথমিক কাস্টমার রিভিউ সিডিং (যদি রিভিউ টেবিল খালি থাকে)
    if c.execute("SELECT COUNT(*) FROM reviews").fetchone()[0] == 0:
        sample_reviews = [
            (1, 'সাকিব আল হাসান', 5, 'AirPods Pro 2 এর আসল লেফট বাড। কেসে রাখার পর এক চান্সে পেয়ার হয়ে গেছে! ১০০% অরিজিনাল সাউন্ড ও অ্যাক্টিভ নয়েজ ক্যান্সেলেশন।'),
            (1, 'মাহমুদুল হক', 5, 'দাম ও ডেলিভারি দুইটাই পারফেক্ট। ঢাকার মধ্যে ২৪ ঘণ্টায় ক্যাশ অন ডেলিভারিতে পেয়েছি।'),
            (2, 'তানজিম আহমেদ', 5, 'একদম ফ্রেশ কন্ডিশন। আইফোনে ব্লুটুথ সেটিংসে অফিসিয়াল সিরিয়াল নম্বর দেখাচ্ছে।'),
            (7, 'আরিফুর রহমান', 5, 'স্যামসাং বাডস ২ প্রো এর রাইট বাড পাচ্ছিলাম না কোথাও। এখান থেকে পেয়ে খুব উপকৃত হলাম।'),
            (12, 'মেহরাব হোসেন', 5, 'সনির WF-1000XM5 এর কোয়ালিটি নিয়ে কোনো সন্দেহ নেই। অসাধারণ সার্ভিস!'),
        ]
        c.executemany("INSERT INTO reviews (product_id, user_name, rating, comment) VALUES (?, ?, ?, ?)", sample_reviews)

    # প্রোডাক্ট টেবিল চেক করে প্রতিটি পণ্যের ইউনিক সিরিয়াল ও সার্টিফিকেট কোড সিড করা
    existing_prods = c.execute("SELECT id, name, brand, model, category FROM products").fetchall()
    for row in existing_prods:
        pid = row[0]
        brand = row[2]
        model = row[3]
        category = row[4]
        b_code = brand[:3].upper()
        cert_code = f"OEM-CERT-{b_code}-{pid:02d}"
        serial = f"BR-OEM-{b_code}-SN{84000 + pid}"
        batch = f"BATCH-2026-Q1-{b_code}"

        # প্রোডাক্ট টেবিলে আপডেট
        c.execute("UPDATE products SET serial_number = ?, certificate_code = ? WHERE id = ?", (serial, cert_code, pid))

        # oem_certificates টেবিলে ইনসার্ট (যদি না থাকে)
        c.execute("""
            INSERT OR IGNORE INTO oem_certificates (
                certificate_code, product_id, brand, model, part_type, serial_number, batch_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        """, (cert_code, pid, brand, model, category, serial, batch))

    # প্রাথমিক ডেমো প্রোডাক্ট সিডিং (যদি প্রোডাক্ট টেবিল খালি থাকে অথবা আপডেট প্রয়োজন হয়)
    current_count = c.execute("SELECT COUNT(*) FROM products").fetchone()[0]
    if current_count < 10:  # পর্যাপ্ত প্রোডাক্ট না থাকলে সমৃদ্ধ ডেমো ক্যাটালগ যোগ করা
        # আগের অসম্পূর্ণ ডাটা মুছে ফ্রেশ ক্যাটালগ বসানো
        c.execute("DELETE FROM products")

        demo_products = [
            # ----------------- APPLE AIRPODS SERIES -----------------
            (
                'Apple AirPods Pro (2nd Gen) — Left Bud',
                'Apple',
                'AirPods Pro 2',
                'Left Bud',
                5890.0,
                7500.0,
                '/static/images/products/airpods-pro-left.svg',
                'আসল অ্যাপল অরিজিনাল লেফট ইয়ারবাড (OEM)। H2 চিপের সাথে অ্যাক্টিভ নয়েজ ক্যান্সেলেশন এবং অ্যাডাপটিভ অডিও সাপোর্ট করে। সম্পূর্ণ টেস্টেড এবং ১ মাসের রিপ্লেসমেন্ট ওয়ারেন্টি।',
                'Original Brand New (OEM)',
                'Model A2698, A2699 (AirPods Pro 2nd Gen USB-C এবং Lightning উভয় কেসের সাথে কমপ্যাটিবল)',
                'উভয় বাড কেসে রেখে ৩০ মিনিট চার্জ দিন। এরপর কেসের পেছনের বাটনটি ১৫ সেকেন্ড চেপে ধরে সাদা লাইট ব্লিঙ্ক করা পর্যন্ত অপেক্ষা করে ফোনে রি-কানেক্ট করুন।',
                12,
                4.9,
                34,
                1
            ),
            (
                'Apple AirPods Pro (2nd Gen) — Right Bud',
                'Apple',
                'AirPods Pro 2',
                'Right Bud',
                5890.0,
                7500.0,
                '/static/images/products/airpods-pro-right.svg',
                'আসল অ্যাপল অরিজিনাল রাইট ইয়ারবাড (OEM)। স্বচ্ছ অডিও, স্পেশিয়াল অডিও হেড ট্র্যাকিং এবং নিখুঁত নয়েজ ক্যান্সেলেশন পাবেন।',
                'Original Brand New (OEM)',
                'Model A2698, A2699 (AirPods Pro 2nd Gen এর জন্য উপযুক্ত)',
                'উভয় বাড কেসে রেখে চার্জ দিন এবং কেসের পেছনের বোতাম চেপে রিস্টার্ট করুন।',
                15,
                4.9,
                29,
                1
            ),
            (
                'Apple AirPods Pro (2nd Gen) — MagSafe Case (USB-C)',
                'Apple',
                'AirPods Pro 2',
                'Charging Case',
                7490.0,
                9200.0,
                '/static/images/products/airpods-pro-case.svg',
                'অরিজিনাল অ্যাপল ম্যাগসেফ ইউএসবি-সি চার্জিং কেস। স্পিকার হোল ও ল্যানইয়ার্ড লুপসহ বিল্ট-ইন ফাইন্ড মাই স্পিকার অ্যালার্ট ফিচারযুক্ত।',
                'Original Brand New (OEM)',
                'Model A2968 (AirPods Pro 2nd Gen এর জন্য প্রযোজ্য)',
                'আপনার বিদ্যমান দুটি বাড এই নতুন কেসে প্রবেশ করান এবং ১০ মিনিট চার্জে রেখে ব্লুটুথে নতুন করে পেয়ার করুন।',
                8,
                5.0,
                18,
                1
            ),
            (
                'Apple AirPods (3rd Gen) — Left Bud',
                'Apple',
                'AirPods 3',
                'Left Bud',
                4490.0,
                5600.0,
                '/static/images/products/airpods3-left.svg',
                'অ্যাপল এয়ারপডস ৩য় প্রজন্মের আসল লেফট বাড। ফোর্স সেন্সর কন্ট্রোল এবং ডলবি অ্যাটমোস স্পেশিয়াল অডিও সমর্থিত।',
                'Original Brand New (OEM)',
                'Model A2565 (AirPods 3rd Generation)',
                'কেসে স্থাপন করে ১৫ সেকেন্ড সেটআপ বাটন চেপে পেয়ার করুন।',
                9,
                4.8,
                21,
                0
            ),
            (
                'Apple AirPods (3rd Gen) — Right Bud',
                'Apple',
                'AirPods 3',
                'Right Bud',
                4490.0,
                5600.0,
                '/static/images/products/airpods3-right.svg',
                'অ্যাপল এয়ারপডস ৩য় প্রজন্মের আসল রাইট বাড। ক্রিস্টাল ক্লিয়ার কল কোয়ালিটি এবং অ্যাকোস্টিক মেশ মাইক্রোফোন।',
                'Original Brand New (OEM)',
                'Model A2564 (AirPods 3rd Generation)',
                'কেসে রেখে ১৫ সেকেন্ড সেটআপ বাটন চেপে পেয়ার করুন।',
                11,
                4.8,
                16,
                0
            ),
            (
                'Apple AirPods (3rd Gen) — Lightning Charging Case',
                'Apple',
                'AirPods 3',
                'Charging Case',
                5790.0,
                6900.0,
                '/static/images/products/airpods3-case.svg',
                'এয়ারপডস ৩ এর জন্য অরিজিনাল লাইটনিং চার্জিং কেস। ৩০ ঘণ্টার বেশি মোট ব্যাটারি লাইফ সাপোর্ট করে।',
                'Original Brand New (OEM)',
                'Model A2897 (AirPods 3rd Gen)',
                'বাড দুটি কেসে ঢুকিয়ে পেয়ারিং বাটন চেপে নতুন সংযোগ স্থাপন করুন।',
                7,
                4.9,
                12,
                0
            ),

            # ----------------- SAMSUNG GALAXY BUDS SERIES -----------------
            (
                'Samsung Galaxy Buds 2 Pro — Left Bud (Graphite)',
                'Samsung',
                'Galaxy Buds 2 Pro',
                'Left Bud',
                4290.0,
                5500.0,
                '/static/images/products/galaxy-buds-left.svg',
                'স্যামসাং গ্যালাক্সি বাডস ২ প্রো অরিজিনাল লেফট ইয়ারবাড। ২৪-বিট হাই-ফাই অডিও, ইন্টেলিজেন্ট এএনসি এবং স্টুডিও গ্রেড সাউন্ড।',
                'Original OEM Tested',
                'Model SM-R510 (Galaxy Buds 2 Pro)',
                'উভয় বাড কেসে রেখে উভয় বাডের টাচপ্যাডে একযোগে ৭ সেকেন্ড আঙুল দিয়ে চেপে রাখুন যতক্ষণ না ইন্ডিকেটর লাইট সবুজ-লাল ব্লিংক করে।',
                14,
                4.9,
                42,
                1
            ),
            (
                'Samsung Galaxy Buds 2 Pro — Right Bud (Graphite)',
                'Samsung',
                'Galaxy Buds 2 Pro',
                'Right Bud',
                4290.0,
                5500.0,
                '/static/images/products/galaxy-buds-right.svg',
                'স্যামসাং গ্যালাক্সি বাডস ২ প্রো অরিজিনাল রাইট ইয়ারবাড। নিখুঁত নয়েজ ক্যান্সেলেশন এবং পানি প্রতিরোধক IPX7 রেটিং।',
                'Original OEM Tested',
                'Model SM-R510 (Galaxy Buds 2 Pro)',
                'Galaxy Wearable অ্যাপে সংযোগ করে কাপলিং প্রসেস সম্পন্ন করুন।',
                10,
                4.9,
                38,
                1
            ),
            (
                'Samsung Galaxy Buds 2 Pro — Charging Case (Graphite)',
                'Samsung',
                'Galaxy Buds 2 Pro',
                'Charging Case',
                4990.0,
                6200.0,
                '/static/images/products/galaxy-buds-case.svg',
                'গ্যালাক্সি বাডস ২ প্রো এর অরিজিনাল চার্জিং কেস। কিউআই ওয়্যারলেস চার্জিং ও ইউএসবি টাইপ-সি ফাস্ট চার্জিং সাপোর্টেড।',
                'Original OEM Tested',
                'Model EP-QR510 (Galaxy Buds 2 Pro)',
                'পুরনো বাড দুটি নতুন কেসে রেখে ১০ মিনিট চার্জ দিলেই পেয়ারিং প্রস্তুত হবে।',
                6,
                4.8,
                19,
                0
            ),
            (
                'Samsung Galaxy Buds FE — Left Bud (White)',
                'Samsung',
                'Galaxy Buds FE',
                'Left Bud',
                2890.0,
                3600.0,
                '/static/images/products/galaxy-buds-left.svg',
                'স্যামসাং গ্যালাক্সি বাডস এফই লেফট রিপ্লেসমেন্ট পার্ট। পাওয়ারফুল ডিপ বাস ও আরামদায়ক উইংটিপ ডিজাইন।',
                'Original OEM Tested',
                'Model SM-R400N (Galaxy Buds FE)',
                'কেসে উভয় বাড রেখে টাচ এরিয়া ৭ সেকেন্ড ধরে রাখলে কাপলিং সম্পন্ন হয়।',
                16,
                4.7,
                23,
                0
            ),
            (
                'Samsung Galaxy Buds FE — Right Bud (White)',
                'Samsung',
                'Galaxy Buds FE',
                'Right Bud',
                2890.0,
                3600.0,
                '/static/images/products/galaxy-buds-right.svg',
                'স্যামসাং গ্যালাক্সি বাডস এফই রাইট রিপ্লেসমেন্ট পার্ট। স্পষ্ট ভয়েস কলিং এবং লং লাস্টিং ব্যাটারি।',
                'Original OEM Tested',
                'Model SM-R400N (Galaxy Buds FE)',
                'কেসে উভয় বাড রেখে টাচ এরিয়া ৭ সেকেন্ড ধরে রাখুন।',
                13,
                4.8,
                17,
                0
            ),

            # ----------------- SONY FLAGSHIP SERIES -----------------
            (
                'Sony WF-1000XM5 — Left Earbud (Black)',
                'Sony',
                'WF-1000XM5',
                'Left Bud',
                6990.0,
                8800.0,
                '/static/images/products/sony-wf-left.svg',
                'সনির ফ্ল্যাগশিপ WF-1000XM5 অরিজিনাল লেফট বাড। ডুয়াল প্রসেসর V2 এবং QN2e নয়েজ ক্যান্সেলিং চিপ সহ বেস্ট-ইন-ক্লাস অডিও।',
                'Original Brand New (OEM)',
                'Model WF-1000XM5 (Left Earphone YY2963)',
                'সনি হেডফোন কানেক্ট অ্যাপ ওপেন করে কেসের পেছনের বাটন ৫ সেকেন্ড চেপে ফ্যাক্টরি পেয়ার রিসেট করুন।',
                8,
                5.0,
                27,
                1
            ),
            (
                'Sony WF-1000XM5 — Right Earbud (Black)',
                'Sony',
                'WF-1000XM5',
                'Right Bud',
                6990.0,
                8800.0,
                '/static/images/products/sony-wf-right.svg',
                'সনির ফ্ল্যাগশিপ WF-1000XM5 অরিজিনাল রাইট বাড। হাই-রেস ওয়্যারলেস এলডিএসি (LDAC) কোডেক সাপোর্টেড প্রিমিয়াম সাউন্ড।',
                'Original Brand New (OEM)',
                'Model WF-1000XM5 (Right Earphone YY2963)',
                'Sony Headphones Connect অ্যাপ ব্যবহার করে পেয়ার সম্পন্ন করুন।',
                7,
                5.0,
                22,
                1
            ),
            (
                'Sony WF-1000XM5 — Charging Case (Black)',
                'Sony',
                'WF-1000XM5',
                'Charging Case',
                7890.0,
                9500.0,
                '/static/images/products/sony-wf-case.svg',
                'সনি ডব্লিউএফ-১০০০এক্সএম৫ অরিজিনাল চার্জিং কেস। প্রিমিয়াম ম্যাট ফিনিশ, ওয়্যারলেস চার্জিং ও ২৪ ঘণ্টার পাওয়ার ব্যাকআপ।',
                'Original Brand New (OEM)',
                'Model YY2963 Case',
                'উভয় বাড কেসে রেখে ২০ সেকেন্ড পেয়ারিং কি চেপে ধরে রিসেট সম্পন্ন করুন।',
                5,
                4.9,
                15,
                0
            ),
            (
                'Sony WF-1000XM4 — Left Earbud (Black)',
                'Sony',
                'WF-1000XM4',
                'Left Bud',
                4790.0,
                6200.0,
                '/static/images/products/sony-wf-left.svg',
                'সনি ডব্লিউএফ-১০০০এক্সএম৪ লেফট বাড রিপ্লেসমেন্ট। সমন্বিত প্রসেসর ভি১ এবং ডিপ পাঞ্চি বাস।',
                'Certified Refurbished (Grade A+)',
                'Model YY2948 (WF-1000XM4)',
                'কেসে বাড দুটি রেখে উভয় বাডের সেন্সরে ১০ সেকেন্ড ধরে রেখে নীল লাইট ফ্ল্যাশ হওয়া পর্যন্ত অপেক্ষা করুন।',
                9,
                4.8,
                31,
                0
            ),

            # ----------------- NOTHING EAR SERIES -----------------
            (
                'Nothing Ear (2) — Left Earbud (White/Clear)',
                'Nothing',
                'Nothing Ear (2)',
                'Left Bud',
                3890.0,
                4900.0,
                '/static/images/products/nothing-ear-left.svg',
                'নাথিং ইয়ার ২ লেফট বাড। আইকনিক ট্রান্সপারেন্ট ডিজাইন, পার্সোনালাইজড সাউন্ড প্রোফাইল এবং হাই-রেস অডিও সার্টিফাইড।',
                'Original Brand New (OEM)',
                'Model B155 (Nothing Ear 2)',
                'কেসে রেখে কেসের পাশের বাটনে ১০ সেকেন্ড চাপ দিন যতক্ষণ না লাল লাইটটি সাদা লাইটে পরিবর্তিত হয়। এরপর Nothing X অ্যাপে কানেক্ট করুন।',
                15,
                4.8,
                45,
                1
            ),
            (
                'Nothing Ear (2) — Right Earbud (White/Clear)',
                'Nothing',
                'Nothing Ear (2)',
                'Right Bud',
                3890.0,
                4900.0,
                '/static/images/products/nothing-ear-right.svg',
                'নাথিং ইয়ার ২ রাইট বাড। অ্যাডভান্সড উইন্ড নয়েজ রিডাকশন এবং স্বচ্ছ ক্লিয়ার ভয়েস টেকনোলজি।',
                'Original Brand New (OEM)',
                'Model B155 (Nothing Ear 2)',
                'Nothing X অ্যাপ ওপেন করে নতুন বাডটি সিঙ্ক করে নিন।',
                12,
                4.9,
                39,
                1
            ),
            (
                'Nothing Ear (2) — Transparent Case',
                'Nothing',
                'Nothing Ear (2)',
                'Charging Case',
                4590.0,
                5800.0,
                '/static/images/products/nothing-ear-case.svg',
                'নাথিং ইয়ার ২ আসল ট্রান্সপারেন্ট চার্জিং কেস। কিউআই ওয়্যারলেস চার্জিং ও আল্ট্রা কমপ্যাক্ট স্কেলিটন বডি।',
                'Original Brand New (OEM)',
                'Model B155 Case',
                'আপনার বাড দুটি কেসে ঢুকিয়ে পাশের পেয়ারিং বাটন চেপে নতুন সংযোগ স্থাপন করুন।',
                6,
                4.9,
                19,
                0
            ),

            # ----------------- ONEPLUS BUDS SERIES -----------------
            (
                'OnePlus Buds Pro 2 — Left Earbud (Obsidian Black)',
                'OnePlus',
                'Buds Pro 2',
                'Left Bud',
                3690.0,
                4800.0,
                '/static/images/products/oneplus-buds-left.svg',
                'ওয়ানপ্লাস বাডস প্রো ২ লেফট রিপ্লেসমেন্ট পার্ট। Dynaudio টিউন করা ডুয়াল ড্রাইভার এবং ৪৮ ডিবি স্মার্ট অ্যাডাপটিভ নয়েজ ক্যান্সেলেশন।',
                'Original Brand New (OEM)',
                'Model E507A (OnePlus Buds Pro 2)',
                'উভয় বাড কেসে রেখে কেসের অভ্যন্তরীণ পেয়ার বোতামটি ১০ সেকেন্ড চেপে রাখুন। সাদা আলো জ্বললে ফোনে পেয়ার করুন।',
                14,
                4.8,
                33,
                1
            ),
            (
                'OnePlus Buds Pro 2 — Right Earbud (Obsidian Black)',
                'OnePlus',
                'Buds Pro 2',
                'Right Bud',
                3690.0,
                4800.0,
                '/static/images/products/oneplus-buds-right.svg',
                'ওয়ানপ্লাস বাডস প্রো ২ রাইট রিপ্লেসমেন্ট পার্ট। ক্রিস্টাল ক্লিয়ার মেটাল স্টেম ও টাচ প্রেশার সেন্সর সাপোর্ট।',
                'Original Brand New (OEM)',
                'Model E507A (OnePlus Buds Pro 2)',
                'HeyMelody অ্যাপের মাধ্যমে সহজে রি-পেয়ারিং নিশ্চিত করুন।',
                11,
                4.8,
                26,
                0
            ),
            (
                'OnePlus Buds Pro 2 — Warp Charge Case',
                'OnePlus',
                'Buds Pro 2',
                'Charging Case',
                4490.0,
                5600.0,
                '/static/images/products/galaxy-buds-case.svg',
                'ওয়ানপ্লাস বাডস প্রো ২ ওয়ার্প চার্জিং কেস। মাত্র ১০ মিনিটের চার্জে ১০ ঘণ্টার ব্যাকআপ দিতে সক্ষম।',
                'Original Brand New (OEM)',
                'Model E507A Case',
                'কেসের বাটন চেপে পেয়ারিং রিসেট করে নিন।',
                8,
                4.9,
                14,
                0
            ),
            (
                'OnePlus Nord Buds 2 — Left Earbud',
                'OnePlus',
                'Nord Buds 2',
                'Left Bud',
                1490.0,
                1990.0,
                '/static/images/products/oneplus-buds-left.svg',
                'ওয়ানপ্লাস নর্ড বাডস ২ অরিজিনাল লেফট ইয়ারবাড। ১২.৪ মিমি টাইটানাইজড ভাইব্রেটিং ড্রাইভারে জোরালো বাস আউটপুট।',
                'Original OEM Tested',
                'Model E508A (OnePlus Nord Buds 2)',
                'কেসে উভয় বাড স্থাপন করে পেয়ারিং বাটন চেপে রি-সিঙ্ক করুন।',
                20,
                4.6,
                52,
                0
            ),

            # ----------------- JBL & GOOGLE SERIES -----------------
            (
                'JBL Live Pro 2 TWS — Left Earbud (Black)',
                'JBL',
                'Live Pro 2',
                'Left Bud',
                2690.0,
                3500.0,
                '/static/images/products/jbl-buds-left.svg',
                'জেবিএল লাইভ প্রো ২ ট্রু ওয়্যারলেস অরিজিনাল লেফট বাড। সিগনেচার জেবিএল পিওর বেস সাউন্ড ও ট্রু অ্যাডাপটিভ নয়েজ ক্যানসেলিং।',
                'Original Brand New (OEM)',
                'Model Live Pro 2 TWS',
                'কেসে রেখে ৩ বার টাচ করুন এবং চতুর্থবারে ৫ সেকেন্ড চেপে ধরে বাড দুটি সিঙ্ক করুন।',
                12,
                4.7,
                28,
                0
            ),
            (
                'JBL Live Pro 2 TWS — Right Earbud (Black)',
                'JBL',
                'Live Pro 2',
                'Right Bud',
                2690.0,
                3500.0,
                '/static/images/products/jbl-buds-right.svg',
                'জেবিএল লাইভ প্রো ২ অরিজিনাল রাইট বাড। ৬ টি পারফেক্ট কল মাইক্রোফোন সমৃদ্ধ ভয়েস ফোকাসিং সিস্টেম।',
                'Original Brand New (OEM)',
                'Model Live Pro 2 TWS',
                'JBL Headphones অ্যাপ ব্যবহার করে পেয়ারিং সম্পন্ন করুন।',
                10,
                4.8,
                24,
                0
            ),
            (
                'Google Pixel Buds Pro — Left Earbud (Charcoal)',
                'Google',
                'Pixel Buds Pro',
                'Left Bud',
                4690.0,
                5900.0,
                '/static/images/products/pixel-buds-left.svg',
                'গুগল পিক্সেল বাডস প্রো অরিজিনাল লেফট ইয়ারবাড। কাস্টম ৬-কোর অডিও চিপের সাথে সাইলেন্ট সিল অ্যাক্টিভ নয়েজ ক্যান্সেলেশন।',
                'Original Brand New (OEM)',
                'Model GA03201 (Pixel Buds Pro)',
                'কেসে উভয় বাড ঢুকিয়ে পেছনের বাটন ৩০ সেকেন্ড চেপে ধরে অরেঞ্জ ও হোয়াইট লাইট শেষ হওয়া পর্যন্ত অপেক্ষা করুন।',
                9,
                4.9,
                19,
                0
            ),
            (
                'Google Pixel Buds Pro — Right Earbud (Charcoal)',
                'Google',
                'Pixel Buds Pro',
                'Right Bud',
                4690.0,
                5900.0,
                '/static/images/products/pixel-buds-right.svg',
                'গুগল পিক্সেল বাডস প্রো অরিজিনাল রাইট ইয়ারবাড। রিয়েল টাইম গুগল ট্রান্সলেট এবং ভলিউম ইকিউ সাপোর্ট।',
                'Original Brand New (OEM)',
                'Model GA03201 (Pixel Buds Pro)',
                'কেসে বাড ঢুকিয়ে গুগল পিক্সেল বাডস অ্যাপে রিকানেক্ট করুন।',
                8,
                4.9,
                15,
                0
            )
        ]

        # বাল্ক ইনসার্ট কুয়েরি চালিয়ে সব প্রোডাক্ট ডাটাবেজে সংরক্ষণ করা
        c.executemany("""
            INSERT INTO products (
                name, brand, model, category, price, original_price,
                image, description, condition, compatibility, pairing_guide,
                stock, rating, reviews_count, is_featured
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, demo_products)

    # ডিফল্ট অ্যাডমিন ইউজার সিডিং (যদি ইউজার টেবিল সম্পূর্ণ খালি থাকে)
    if c.execute("SELECT COUNT(*) FROM users").fetchone()[0] == 0:
        c.execute("""
            INSERT INTO users (name, email, password, phone, address, is_admin)
            VALUES (?, ?, ?, ?, ?, 1)
        """, (
            'Store Admin',                      # অ্যাডমিনের নাম
            'admin@budsrecover.com',             # অ্যাডমিন লগইন ইমেইল
            generate_password_hash('admin123'),  # ডিফল্ট পাসওয়ার্ড (admin123) হ্যাশ করে সেভ করা
            '01700000000',                       # অ্যাডমিন কন্টাক্ট নম্বর
            'Dhanmondi, Dhaka, Bangladesh'       # ঠিকানা
        ))

    # পরিবর্তনগুলো ডাটাবেজে পার্মানেন্টভাবে সেভ (Commit) করা
    conn.commit()
    # ডাটাবেজ কানেকশন ক্লোজ করা
    conn.close()

if __name__ == '__main__':
    # এই ফাইল সরাসরি রান করা হলে টেবিল তৈরি ও সিডিং পরীক্ষা করা হবে
    print("Initializing BudsRecover Database...")
    init_db()
    print("Database tables initialized and demo products seeded successfully!")
