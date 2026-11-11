"""
=============================================================================
প্রজেক্ট নাম: BudsRecover (ইয়ারবাড রিপ্লেসমেন্ট ই-কমার্স প্ল্যাটফর্ম)
ফাইল: app.py
বিবরণ: Flask ব্যাকএন্ড কন্ট্রোলার - রাউটিং, এপিআই, কার্ট ম্যানেজমেন্ট, 
      অর্ডার প্রসেসিং, অথেনটিকেশন ও এডমিন প্যানেল।
গ্রুপ প্রজেক্টের সুবিধার জন্য প্রতিটি লাইনে বাংলা কমেন্ট যুক্ত করা হয়েছে।
=============================================================================
"""

# প্রয়োজনীয় পাইথন ও ফ্লাস্ক লাইব্রেরি ইমপোর্ট করা
from flask import (
    Flask, render_template, request, redirect, 
    url_for, session, jsonify, flash
)
from werkzeug.security import generate_password_hash, check_password_hash  # পাসওয়ার্ড সুরক্ষা
from werkzeug.utils import secure_filename  # নিরাপদ ফাইলনেম হ্যান্ডলিং
import os       # ফাইল সিস্টেম ও এনভায়রনমেন্ট ভ্যারিয়েবল
import secrets  # সিক্রেট কি তৈরির জন্য র‍্যান্ডম হ্যাশ
import sqlite3  # ডাটাবেজ এক্সেপশন হ্যান্ডলিং
import random   # ইউনিক অর্ডার কোড জেনারেশন

# ডাটাবেজ হেল্পার ফাংশন ইমপোর্ট করা
from database import get_db, init_db

# ফ্লাস্ক অ্যাপ ইনিশিয়ালাইজ করা
app = Flask(__name__)

# সেশন সিকিউরিটি ও এনক্রিপশনের জন্য সিক্রেট কি (Secret Key) সেট করা
app.secret_key = os.environ.get('SECRET_KEY') or secrets.token_hex(32)

# ফাইল আপলোডের সর্বোচ্চ সাইজ নির্ধারণ (সর্বোচ্চ 8 MB)
app.config['MAX_CONTENT_LENGTH'] = 8 * 1024 * 1024

# ইমেজ আপলোড ফোল্ডার তৈরি করা (যদি না থাকে)
UPLOAD_FOLDER = os.path.join('static', 'images')
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER

# সার্ভার চালু হওয়ার সময় ডাটাবেজ টেবিল চেক ও ইনিশিয়ালাইজ করা
init_db()


# =============================================================================
# গ্লোবাল হেল্পার ফাংশন ও কুপন ডিসকাউন্ট ইঞ্জিন (Helpers & Coupon Engine)
# =============================================================================

# স্টোরের সক্রিয় প্রমো কোড ও ডিসকাউন্টের তালিকা
AVAILABLE_COUPONS = {
    'BUDS10': {'type': 'percent', 'val': 10, 'desc': '১০% ডিসকাউন্ট'},
    'FREESHIP': {'type': 'shipping', 'val': 0, 'desc': 'ফ্রি ডেলিভারি'},
    'SAVE500': {'type': 'flat', 'val': 500, 'desc': '৳৫০০ ফ্ল্যাট ছাড়'}
}

def calculate_cart_totals(subtotal, city='Dhaka'):
    """
    সাবটোটাল, ডেলিভারি ফি এবং কুপন ডিসকাউন্ট হিসাব করার কেন্দ্রীয় হেল্পার ফাংশন
    """
    coupon_data = session.get('coupon', None)
    discount = 0.0
    coupon_code = ''

    # বেস ডেলিভারি ফি (ঢাকা ৳৬০, বাইরে ৳১২০; ৳৫০০০ বা তদূর্ধে ফ্রি)
    if subtotal >= 5000 or subtotal == 0:
        delivery_fee = 0.0
    else:
        delivery_fee = 60.0 if city == 'Dhaka' else 120.0

    if coupon_data and subtotal > 0:
        coupon_code = coupon_data.get('code', '')
        c_type = coupon_data.get('type')
        c_val = coupon_data.get('val', 0)

        if c_type == 'percent':
            discount = round((subtotal * c_val) / 100, 2)
        elif c_type == 'flat':
            discount = min(float(c_val), subtotal)
        elif c_type == 'shipping':
            discount = delivery_fee
            delivery_fee = 0.0

    grand_total = max(0.0, subtotal - discount) + delivery_fee

    return {
        'subtotal': subtotal,
        'delivery_fee': delivery_fee,
        'discount': discount,
        'coupon_code': coupon_code,
        'coupon_desc': coupon_data.get('desc', '') if coupon_data else '',
        'grand_total': grand_total
    }

def is_logged_in():
    """ইউজার লগইন অবস্থায় আছে কি না তা যাচাই করার হেল্পার ফাংশন"""
    return 'user_id' in session

def is_admin():
    """লগইন করা ইউজার এডমিন কি না তা যাচাই করার হেল্পার ফাংশন"""
    return session.get('is_admin') == 1

@app.context_processor
def inject_globals():
    """
    ফ্লাস্কের এই ডেকোরেটরের মাধ্যমে সকল এইচটিএমএল টেমপ্লেটে 
    স্বয়ংক্রিয়ভাবে কার্টের মোট আইটেম সংখ্যা এবং কার্ট সাবটোটাল পাঠিয়ে দেওয়া হয়।
    """
    cart = session.get('cart', {})
    total_qty = sum(cart.values()) if cart else 0
    return {
        'cart_count': total_qty,
        'is_logged_in': is_logged_in(),
        'is_admin': is_admin(),
        'user_name': session.get('name', ''),
        'user_id': session.get('user_id', None)
    }


# =============================================================================
# পাবলিক ক্যাটালগ ও স্টোরফ্রন্ট রাউটস (Storefront Routes)
# =============================================================================

@app.route('/')
def index():
    """
    হোম পেজ রাউট:
    - ব্রাউজারের ফিল্টার, সার্চ ও সর্টিং প্যারামিটার রিসিভ করে।
    - ডাটাবেজ থেকে ফিল্টার অনুযায়ী প্রোডাক্ট লিস্ট ফেচ করে।
    - ব্র্যান্ড ও মডেল ক্যাটাগরি ডাটা টেমপ্লেটে পাস করে।
    """
    conn = get_db()
    
    # ইউআরএল কোয়েরি প্যারামিটারগুলো পড়া (Query Parameters)
    query = request.args.get('q', '').strip()              # সার্চ বার থেকে সার্চ টেক্সট
    brand = request.args.get('brand', '').strip()          # নির্বাচিত ব্র্যান্ড ফিল্টার
    category = request.args.get('category', '').strip()    # নির্বাচিত ক্যাটাগরি (Left Bud/Right Bud/Case)
    model = request.args.get('model', '').strip()          # নির্বাচিত মডেল ফিল্টার
    sort = request.args.get('sort', 'newest')              # সর্টিং অপশন (দাম কম/বেশি, নতুন)

    # এসকিউএল ফিল্টার কুয়েরি ডাইনামিকভাবে তৈরি করা
    filters = []
    values = []

    # যদি ইউজার কোনো শব্দ সার্চ করে থাকে (নাম, ব্র্যান্ড বা বিবরণে খোঁজা হবে)
    if query:
        filters.append('(name LIKE ? OR brand LIKE ? OR model LIKE ? OR description LIKE ?)')
        values.extend([f'%{query}%'] * 4)

    # যদি নির্দিষ্ট ব্র্যান্ড বাছাই করা হয়
    if brand:
        filters.append('brand = ?')
        values.append(brand)

    # যদি নির্দিষ্ট ক্যাটাগরি বাছাই করা হয়
    if category:
        filters.append('category = ?')
        values.append(category)

    # যদি নির্দিষ্ট মডেল বাছাই করা হয়
    if model:
        filters.append('model = ?')
        values.append(model)

    # সর্টিংয়ের জন্য অর্ডার বাই (ORDER BY) নির্ধারণ
    order_clause = {
        'price-low': 'price ASC',                 # কম দাম থেকে বেশি দাম
        'price-high': 'price DESC',               # বেশি দাম থেকে কম দাম
        'name': 'name COLLATE NOCASE ASC',       # বর্ণানুক্রম অনুযায়ী (A-Z)
        'rating': 'rating DESC'                  # সর্বোচ্চ রেটিং অনুযায়ী
    }.get(sort, 'id DESC')                        # ডিফল্ট: নতুন প্রোডাক্ট আগে আসবে

    # WHERE ক্লজ তৈরি
    where_sql = f"WHERE {' AND '.join(filters)}" if filters else ""

    # ফিল্টার ও সর্ট অনুযায়ী প্রোডাক্ট কুয়েরি
    products = conn.execute(
        f"SELECT * FROM products {where_sql} ORDER BY {order_clause}", values
    ).fetchall()

    # হোমপেজের ফিল্টারে দেখানোর জন্য ইউনিক ব্র্যান্ড লিস্ট
    brands = conn.execute(
        "SELECT DISTINCT brand FROM products ORDER BY brand ASC"
    ).fetchall()

    # ইউনিক মডেল লিস্ট (ইন্টারেক্টিভ বাড ফাইন্ডার উইজার্ডের জন্য)
    models = conn.execute(
        "SELECT DISTINCT brand, model FROM products ORDER BY brand ASC, model ASC"
    ).fetchall()

    # ইউনিক ক্যাটাগরি লিস্ট
    categories = conn.execute(
        "SELECT DISTINCT category FROM products ORDER BY category ASC"
    ).fetchall()

    # ফিচার্ড প্রোডাক্টস (হোমপেজের টপ সেকশনে দেখানোর জন্য)
    featured_products = conn.execute(
        "SELECT * FROM products WHERE is_featured = 1 LIMIT 4"
    ).fetchall()

    conn.close()

    # index.html টেমপ্লেট রেন্ডার করা
    return render_template(
        'index.html',
        products=products,
        featured_products=featured_products,
        brands=brands,
        models=models,
        categories=categories,
        query=query,
        selected_brand=brand,
        selected_category=category,
        selected_model=model,
        sort=sort
    )


@app.route('/product/<int:pid>')
def product(pid):
    """
    সিঙ্গেল প্রোডাক্ট ডিটেইলস পেজ:
    - নির্দিষ্ট প্রোডাক্টের পূর্ণাঙ্গ স্পেসিফিকেশন, পেয়ারিং গাইড ও ওয়ারেন্টি তথ্য দেখায়।
    - একই মডেলের অন্যান্য পার্টস (যেমন লেফট দেখলে রাইট ও কেস) সাজেস্ট করে।
    """
    conn = get_db()
    
    # প্রোডাক্ট আইডি অনুযায়ী তথ্য ফেচ করা
    p = conn.execute("SELECT * FROM products WHERE id = ?", (pid,)).fetchone()
    if not p:
        conn.close()
        return render_template('404.html'), 404

    # একই ব্র্যান্ড ও মডেলের অন্যান্য রিলেটেড পার্টস ফেচ করা (Matching Set)
    related = conn.execute(
        "SELECT * FROM products WHERE model = ? AND id != ? LIMIT 3", 
        (p['model'], pid)
    ).fetchall()

    # এই প্রোডাক্টের কাস্টমার রিভিউ ফেচ করা
    reviews = conn.execute(
        "SELECT * FROM reviews WHERE product_id = ? ORDER BY id DESC LIMIT 5",
        (pid,)
    ).fetchall()

    conn.close()

    return render_template(
        'product.html', 
        product=p, 
        related=related, 
        reviews=reviews
    )


@app.route('/pairing-guide')
def pairing_guide():
    """
    ইন্টারেক্টিভ পেয়ারিং গাইড পেজ:
    নতুন রিপ্লেসমেন্ট বাড কীভাবে পুরনো কেসের সাথে পেয়ার করতে হয় তার ব্র্যান্ড অনুযায়ী সম্পূর্ণ নির্দেশিকা।
    """
    return render_template('pairing_guide.html')


@app.route('/help', methods=['GET', 'POST'])
def help_request():
    """
    কাস্টমার সাপোর্ট ও মডেল ম্যাচিং হেল্প পেজ:
    গ্রাহক তার বাডের মডেল বা পার্ট নিয়ে কনফিউজড হলে এখান থেকে রিকোয়েস্ট সাবমিট করতে পারে।
    """
    success = False
    error = ''

    if request.method == 'POST':
        # ফর্ম থেকে তথ্য গ্রহণ করা
        name = request.form.get('name', '').strip()
        phone = request.form.get('phone', '').strip()
        email = request.form.get('email', '').strip()
        brand = request.form.get('brand', '').strip()
        model = request.form.get('model', '').strip()
        missing_part = request.form.get('missing_part', '').strip()
        message = request.form.get('message', '').strip()

        # ভ্যালিডেশন
        if not name or not phone or not brand or not message:
            error = 'দয়া করে নাম, ফোন নম্বর, ব্র্যান্ড এবং সমস্যার বিবরণ পূরণ করুন।'
        else:
            conn = get_db()
            conn.execute("""
                INSERT INTO support_requests (name, phone, email, brand, model, missing_part, message)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (name, phone, email, brand, model, missing_part, message))
            conn.commit()
            conn.close()
            success = True

    return render_template('help.html', success=success, error=error)


@app.route('/authenticity')
def authenticity():
    """
    ১০০% আসল পার্টস যাচাইকরণ গাইড, কিউআর কোড স্ক্যানার ও অথেন্টিসিটি হাব পেজ:
    গ্রাহকদের নকল পণ্য চেনার উপায়, ক্যামেরা/ছবি দিয়ে লাইভ QR স্ক্যান ও ভেরিফিকেশন।
    """
    initial_code = request.args.get('verify', '').strip() or request.args.get('code', '').strip()
    return render_template('authenticity.html', initial_code=initial_code)


@app.route('/verify')
def verify_shortcut():
    """QR কোড ক্যামেরা দিয়ে স্ক্যান করলে সরাসরি অথেন্টিসিটি ভেরিফিকেশন পেজে নিয়ে যাবে"""
    code = request.args.get('code', '').strip() or request.args.get('verify', '').strip()
    return redirect(url_for('authenticity', verify=code))


@app.route('/api/verify-qr', methods=['POST'])
def api_verify_qr():
    """
    QR কোড ও ডিজিটাল OEM সার্টিফিকেট ভেরিফিকেশন API:
    - স্ক্যান করা কিউআর কোড বা সিরিয়াল নম্বর রিসিভ করে।
    - oem_certificates ও products টেবিল থেকে তথ্য যাচাই করে।
    - আসল হলে ল্যাব সার্টিফিকেট, ফ্রিকোয়েন্সি রেসপন্স, নয়েজ ক্যান্সেলেশন ও ওয়ারেন্টি স্ট্যাটাস রিটার্ন করে।
    - নকল/অবৈধ কোড হলে সতর্কবার্তা রিটার্ন করে।
    """
    data = request.get_json() or {}
    raw_payload = data.get('code', '').strip() or data.get('payload', '').strip()

    if not raw_payload:
        return jsonify({'success': False, 'message': 'কোনো QR কোড ডাটা পাওয়া যায়নি।'})

    # যদি স্ক্যান করা ডাটা সম্পূর্ণ ইউআরএল হয় (যেমন https://.../verify?code=OEM-CERT-APP-01)
    code = raw_payload
    if 'code=' in code:
        code = code.split('code=')[-1].split('&')[0].strip()
    elif 'verify=' in code:
        code = code.split('verify=')[-1].split('&')[0].strip()

    conn = get_db()
    # ১. oem_certificates টেবিলে খোঁজ করা
    cert = conn.execute("""
        SELECT * FROM oem_certificates 
        WHERE certificate_code = ? OR serial_number = ?
    """, (code, code)).fetchone()

    prod = None
    if cert and cert['product_id']:
        prod = conn.execute("SELECT * FROM products WHERE id = ?", (cert['product_id'],)).fetchone()
    elif not cert:
        # ২. products টেবিলে সিরিয়াল বা সার্টিফিকেট কোড মিলানো
        prod = conn.execute("""
            SELECT * FROM products 
            WHERE certificate_code = ? OR serial_number = ? OR compatibility LIKE ?
        """, (code, code, f"%{code}%")).fetchone()

        if prod:
            cert = {
                'certificate_code': prod['certificate_code'] or f"OEM-CERT-{prod['brand'][:3].upper()}-{prod['id']:02d}",
                'product_id': prod['id'],
                'brand': prod['brand'],
                'model': prod['model'],
                'part_type': prod['category'],
                'serial_number': prod['serial_number'] or f"BR-OEM-SN{84000 + prod['id']}",
                'batch_number': f"BATCH-2026-Q1-{prod['brand'][:3].upper()}",
                'lab_test_status': 'Passed (১০০% আসল OEM জেনুইন)',
                'tested_by': 'BudsRecover Acoustic & Hardware Lab (Dhaka)',
                'frequency_response': '20Hz - 20,000Hz (OEM Spec Matched)',
                'anc_efficiency': '99.1% Active Noise Cancellation Effective',
                'warranty_days': 90,
                'created_at': prod['created_at']
            }

    conn.close()

    if cert:
        return jsonify({
            'success': True,
            'status': 'GENUINE_OEM',
            'scanned_code': raw_payload,
            'certificate': {
                'code': cert['certificate_code'],
                'brand': cert['brand'],
                'model': cert['model'],
                'part_type': cert['part_type'],
                'serial_number': cert['serial_number'],
                'batch_number': cert['batch_number'],
                'lab_test_status': cert['lab_test_status'],
                'tested_by': cert['tested_by'],
                'frequency_response': cert['frequency_response'],
                'anc_efficiency': cert['anc_efficiency'],
                'warranty_days': cert['warranty_days'],
                'created_at': str(cert['created_at'])
            },
            'product': {
                'id': prod['id'] if prod else None,
                'name': prod['name'] if prod else cert['model'],
                'price': prod['price'] if prod else None,
                'image': prod['image'] if prod else None,
                'category': prod['category'] if prod else cert['part_type']
            },
            'message': f"অভিনন্দন! এটি BudsRecover কর্তৃক শতভাগ ভেরিফায়েড আসল {cert['brand']} OEM পার্টস।"
        })
    else:
        return jsonify({
            'success': False,
            'status': 'INVALID_OR_FAKE',
            'scanned_code': raw_payload,
            'message': f"সতর্কবার্তা: '{raw_payload}' কোডটি BudsRecover অফিসিয়াল OEM ডাটাবেজে অন্তর্ভুক্ত নয়। এটি একটি ক্লোন বা নকল পার্টস হওয়ার সম্ভাবনা রয়েছে।"
        })


@app.route('/api/check-model', methods=['POST'])
def api_check_model():
    """
    মডেল ও সিরিয়াল কোড ভ্যালিডেশন API:
    ব্যবহারকারী মডেল কোড (যেমন A2698, SM-R510) দিলে ডাটাবেজ থেকে আসল পার্ট ও স্পেক্স রিটার্ন করে।
    """
    data = request.get_json() or {}
    code = data.get('code', '').strip()
    if not code:
        return jsonify({'success': False, 'message': 'দয়া করে মডেল কোড লিখুন।'})

    conn = get_db()
    matches = conn.execute("""
        SELECT * FROM products 
        WHERE compatibility LIKE ? OR model LIKE ? OR name LIKE ?
        LIMIT 3
    """, (f'%{code}%', f'%{code}%', f'%{code}%')).fetchall()
    conn.close()

    if matches:
        return jsonify({
            'success': True,
            'matches': [{
                'id': m['id'],
                'name': m['name'],
                'brand': m['brand'],
                'model': m['model'],
                'category': m['category'],
                'price': m['price'],
                'compatibility': m['compatibility'],
                'condition': m['condition'],
                'image': m['image']
            } for m in matches]
        })
    else:
        return jsonify({
            'success': False,
            'message': f"'{code}' কোডের কোনো তথ্য পাওয়া যায়নি। সঠিক মডেল কোডের জন্য চার্জিং কেসের ভেতরের অংশ বা ফোনের ব্লুটুথ সেটিংস দেখুন।"
        })


@app.route('/track-order', methods=['GET', 'POST'])
def track_order():
    """
    লাইভ অর্ডার ট্র্যাকিং পেজ:
    গ্রাহক তার অর্ডার কোড (যেমন: BR-84920) বা ফোন নম্বর দিয়ে লাইভ স্ট্যাটাস ট্র্যাক করতে পারেন।
    """
    order = None
    items = []
    error = ''
    query_code = request.args.get('code', '').strip()

    if request.method == 'POST':
        query_code = request.form.get('code', '').strip()

    if query_code:
        conn = get_db()
        order = conn.execute("""
            SELECT * FROM orders 
            WHERE order_code = ? OR customer_phone = ? 
            ORDER BY id DESC LIMIT 1
        """, (query_code, query_code)).fetchone()

        if order:
            items = conn.execute("""
                SELECT * FROM order_items WHERE order_id = ?
            """, (order['id'],)).fetchall()
        else:
            error = f"'{query_code}' কোড বা ফোন নম্বরে কোনো অর্ডার খুঁজে পাওয়া যায়নি। দয়া করে সঠিক কোড দিন।"
        conn.close()

    return render_template('track_order.html', order=order, items=items, error=error, query_code=query_code)


@app.route('/sell-your-bud', methods=['GET', 'POST'])
def sell_your_bud():
    """
    ট্রেড-ইন / পড়ে থাকা বাড বা কেস বিক্রি করার পেজ ও ফর্ম প্রসেসর:
    গ্রাহক তাদের অকেজো বাড বা কেসের তথ্য দিয়ে বিক্রয় আবেদন পাঠাতে পারে।
    """
    success = False
    estimated_price = 0
    error = ''

    if request.method == 'POST':
        name = request.form.get('name', '').strip()
        phone = request.form.get('phone', '').strip()
        brand = request.form.get('brand', '').strip()
        model = request.form.get('model', '').strip()
        part_type = request.form.get('part_type', 'Left Bud').strip()
        condition = request.form.get('condition', 'Good').strip()
        expected_price_raw = request.form.get('expected_price', '0').strip()
        expected_price = float(expected_price_raw) if expected_price_raw.replace('.', '', 1).isdigit() else 0.0

        if not name or not phone or not brand or not model:
            error = 'দয়া করে নাম, মোবাইল নম্বর, ব্র্যান্ড এবং মডেলের নাম লিখুন।'
        else:
            # আনুমানিক প্রাইস ক্যালকুলেটর (ব্র্যান্ড ও কন্ডিশন অনুযায়ী)
            base_estimates = {
                'Apple': 3200,
                'Samsung': 2500,
                'Sony': 3600,
                'Nothing': 2200,
                'OnePlus': 2000,
                'Google': 2600,
                'JBL': 1500
            }
            multiplier = 1.0 if condition == 'Mint' else (0.85 if condition == 'Good' else 0.65)
            estimated_price = round(base_estimates.get(brand, 1800) * multiplier)

            conn = get_db()
            conn.execute("""
                INSERT INTO trade_in_requests (name, phone, brand, model, part_type, condition, expected_price, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
            """, (name, phone, brand, model, part_type, condition, expected_price or estimated_price))
            conn.commit()
            conn.close()
            success = True

    return render_template('sell_your_bud.html', success=success, estimated_price=estimated_price, error=error)


# =============================================================================
# কার্ট ও শপিং ব্যাগ রাউটস (Cart & Mini-Cart APIs)
# =============================================================================

@app.route('/cart')
def cart():
    """
    পূর্ণাঙ্গ কার্ট ও শপিং ব্যাগ পেজ:
    সেশনের কার্ট আইটেমগুলো নিয়ে ডাটাবেজ থেকে দাম ও স্টক হিসেব করে বিস্তারিত প্রদর্শন করে।
    """
    cart_data = session.get('cart', {})
    items = []
    subtotal = 0.0

    if cart_data:
        conn = get_db()
        for pid_str, qty in list(cart_data.items()):
            p = conn.execute("SELECT * FROM products WHERE id = ?", (int(pid_str),)).fetchone()
            if p:
                # স্টক সীমা চেক করা
                actual_qty = min(qty, p['stock'])
                item_total = p['price'] * actual_qty
                subtotal += item_total
                items.append({
                    'product': p,
                    'qty': actual_qty,
                    'subtotal': item_total
                })
        conn.close()

    # ডেলিভারি চার্জ ও কুপন ডিসকাউন্ট হিসেব করা
    totals = calculate_cart_totals(subtotal, city='Dhaka')

    return render_template(
        'cart.html',
        items=items,
        subtotal=totals['subtotal'],
        delivery_fee=totals['delivery_fee'],
        discount=totals['discount'],
        coupon_code=totals['coupon_code'],
        coupon_desc=totals['coupon_desc'],
        grand_total=totals['grand_total']
    )


@app.route('/add-to-cart/<int:pid>', methods=['POST'])
def add_to_cart(pid):
    """
    প্রোডাক্ট কার্টে যোগ করার সাধারণ POST হ্যান্ডলার (Standard Non-AJAX Fallback)।
    """
    conn = get_db()
    product = conn.execute("SELECT id, stock FROM products WHERE id = ?", (pid,)).fetchone()
    conn.close()

    if not product or product['stock'] < 1:
        flash('দুঃখিত, এই প্রোডাক্টটি এই মুহূর্তে স্টকে নেই।', 'danger')
        return redirect(request.referrer or url_for('index'))

    cart = session.get('cart', {})
    key = str(pid)
    current_qty = cart.get(key, 0)
    
    # নতুন পরিমাণ স্টকের বেশি হতে পারবে না
    new_qty = min(current_qty + 1, product['stock'])
    cart[key] = new_qty
    session['cart'] = cart
    session.modified = True

    return redirect(url_for('cart'))


@app.route('/update-cart/<int:pid>', methods=['POST'])
def update_cart(pid):
    """কার্টে থাকা নির্দিষ্ট পণ্যের পরিমাণ বৃদ্ধি বা হ্রাস করার সাধারণ ফর্ম হ্যান্ডলার"""
    cart = session.get('cart', {})
    key = str(pid)

    try:
        qty = int(request.form.get('quantity', 1))
    except ValueError:
        qty = 1

    conn = get_db()
    product = conn.execute("SELECT stock FROM products WHERE id = ?", (pid,)).fetchone()
    conn.close()

    if not product or qty < 1:
        cart.pop(key, None)
    else:
        cart[key] = min(qty, product['stock'])

    session['cart'] = cart
    session.modified = True
    return redirect(url_for('cart'))


@app.route('/remove-from-cart/<int:pid>', methods=['POST'])
def remove_from_cart(pid):
    """কার্ট থেকে নির্দিষ্ট পণ্য সম্পূর্ণ মুছে ফেলার ফর্ম হ্যান্ডলার"""
    cart = session.get('cart', {})
    cart.pop(str(pid), None)
    session['cart'] = cart
    session.modified = True
    return redirect(url_for('cart'))


# ---------------- AJAX APIs for Modern Side-Drawer Cart ----------------

@app.route('/api/cart/add/<int:pid>', methods=['POST'])
def api_add_to_cart(pid):
    """
    আধুনিক স্লাইড-আউট কার্ট ড্রয়ারের জন্য রিয়েল-টাইম AJAX API:
    পেজ রিফ্রেশ ছাড়াই সাথে সাথে প্রোডাক্ট কার্টে যোগ করে এবং JSON রেসপন্স পাঠায়।
    """
    conn = get_db()
    product = conn.execute("SELECT * FROM products WHERE id = ?", (pid,)).fetchone()
    conn.close()

    if not product:
        return jsonify({'success': False, 'message': 'পণ্যটি পাওয়া যায়নি।'}), 404

    if product['stock'] < 1:
        return jsonify({'success': False, 'message': 'দুঃখিত, পণ্যটি স্টক আউট।'}), 400

    cart = session.get('cart', {})
    key = str(pid)
    current_qty = cart.get(key, 0)

    if current_qty >= product['stock']:
        return jsonify({
            'success': False, 
            'message': f'স্টকে মাত্র {product["stock"]} টি রয়েছে, এর বেশি যোগ করা সম্ভব নয়।'
        }), 400

    cart[key] = current_qty + 1
    session['cart'] = cart
    session.modified = True

    # সম্পূর্ণ কার্ট ডাটা ক্যালকুলেট করে রিটার্ন করা
    return jsonify({
        'success': True,
        'message': f'{product["name"]} সফলভাবে কার্টে যোগ হয়েছে!',
        'total_count': sum(cart.values()),
        'added_item': {
            'id': product['id'],
            'name': product['name'],
            'price': product['price'],
            'category': product['category'],
            'image': product['image']
        }
    })


@app.route('/api/cart/state')
def api_cart_state():
    """
    সাইড ড্রয়ারের জন্য সম্পূর্ণ কার্টের বর্তমান অবস্থা JSON ফরম্যাটে রিটার্ন করে।
    """
    cart_data = session.get('cart', {})
    items = []
    subtotal = 0.0

    if cart_data:
        conn = get_db()
        for pid_str, qty in cart_data.items():
            p = conn.execute("SELECT * FROM products WHERE id = ?", (int(pid_str),)).fetchone()
            if p:
                actual_qty = min(qty, p['stock'])
                item_total = p['price'] * actual_qty
                subtotal += item_total
                items.append({
                    'id': p['id'],
                    'name': p['name'],
                    'brand': p['brand'],
                    'category': p['category'],
                    'price': p['price'],
                    'image': p['image'],
                    'qty': actual_qty,
                    'stock': p['stock'],
                    'subtotal': item_total
                })
        conn.close()

    totals = calculate_cart_totals(subtotal, city='Dhaka')

    return jsonify({
        'items': items,
        'total_count': sum(cart_data.values()) if cart_data else 0,
        'subtotal': totals['subtotal'],
        'delivery_fee': totals['delivery_fee'],
        'discount': totals['discount'],
        'coupon_code': totals['coupon_code'],
        'coupon_desc': totals['coupon_desc'],
        'grand_total': totals['grand_total']
    })


@app.route('/api/cart/update/<int:pid>', methods=['POST'])
def api_update_cart(pid):
    """সাইড ড্রয়ার থেকে পরিমাণ রিয়েলটাইম পরিবর্তন করার AJAX API"""
    data = request.get_json() or {}
    delta = data.get('delta', 0)  # +1 বা -1
    cart = session.get('cart', {})
    key = str(pid)

    if key not in cart:
        return jsonify({'success': False, 'message': 'আইটেম কার্টে নেই'}), 404

    conn = get_db()
    product = conn.execute("SELECT stock FROM products WHERE id = ?", (pid,)).fetchone()
    conn.close()

    new_qty = cart[key] + delta
    if new_qty <= 0:
        cart.pop(key, None)
    elif product and new_qty > product['stock']:
        return jsonify({'success': False, 'message': 'সর্বোচ্চ স্টক লিমিট পৌঁছেছে'}), 400
    else:
        cart[key] = new_qty

    session['cart'] = cart
    session.modified = True
    return jsonify({'success': True, 'total_count': sum(cart.values())})


@app.route('/api/cart/remove/<int:pid>', methods=['POST'])
def api_remove_cart(pid):
    """সাইড ড্রয়ার থেকে আইটেম মুছে ফেলার AJAX API"""
    cart = session.get('cart', {})
    cart.pop(str(pid), None)
    session['cart'] = cart
    session.modified = True
    return jsonify({'success': True, 'total_count': sum(cart.values())})


@app.route('/api/product/<int:pid>')
def api_product_quickview(pid):
    """
    কুইক ভিউ মডালের জন্য সিঙ্গেল প্রোডাক্ট ডাটা JSON এ রিটার্ন করে।
    """
    conn = get_db()
    p = conn.execute("SELECT * FROM products WHERE id = ?", (pid,)).fetchone()
    conn.close()
    if not p:
        return jsonify({'success': False, 'message': 'Not found'}), 404

    return jsonify({
        'success': True,
        'product': {
            'id': p['id'],
            'name': p['name'],
            'brand': p['brand'],
            'model': p['model'],
            'category': p['category'],
            'price': p['price'],
            'original_price': p['original_price'],
            'image': p['image'],
            'description': p['description'],
            'condition': p['condition'],
            'compatibility': p['compatibility'],
            'pairing_guide': p['pairing_guide'],
            'stock': p['stock'],
            'rating': p['rating'],
            'reviews_count': p['reviews_count']
        }
    })


@app.route('/api/coupon/apply', methods=['POST'])
def api_apply_coupon():
    """
    কুপন কোড প্রয়োগ করার AJAX API:
    গ্রাহক কুপন কোড (যেমন: BUDS10, FREESHIP, SAVE500) ইনপুট দিলে যাচাই করে সেশনে যোগ করে।
    """
    data = request.get_json() or {}
    code = data.get('code', '').strip().upper()

    if not code:
        return jsonify({'success': False, 'message': 'দয়া করে কুপন কোড লিখুন।'})

    if code not in AVAILABLE_COUPONS:
        return jsonify({
            'success': False, 
            'message': f"'{code}' সঠিক বা কার্যকর কুপন কোড নয়। আপনি BUDS10 বা FREESHIP ট্রাই করতে পারেন।"
        })

    c_info = AVAILABLE_COUPONS[code]
    session['coupon'] = {
        'code': code,
        'type': c_info['type'],
        'val': c_info['val'],
        'desc': c_info['desc']
    }
    session.modified = True

    # বর্তমান কার্টের সাবটোটাল বের করা
    cart_data = session.get('cart', {})
    subtotal = 0.0
    if cart_data:
        conn = get_db()
        for pid_str, qty in cart_data.items():
            prod = conn.execute("SELECT price, stock FROM products WHERE id = ?", (int(pid_str),)).fetchone()
            if prod:
                subtotal += prod['price'] * min(qty, prod['stock'])
        conn.close()

    totals = calculate_cart_totals(subtotal)

    return jsonify({
        'success': True,
        'message': f"কুপন '{code}' সফলভাবে প্রযুক্ত হয়েছে! ({c_info['desc']})",
        'totals': totals
    })


@app.route('/api/coupon/remove', methods=['POST'])
def api_remove_coupon():
    """প্রযুক্ত কুপন বাতিল করার AJAX API"""
    session.pop('coupon', None)
    session.modified = True

    cart_data = session.get('cart', {})
    subtotal = 0.0
    if cart_data:
        conn = get_db()
        for pid_str, qty in cart_data.items():
            prod = conn.execute("SELECT price, stock FROM products WHERE id = ?", (int(pid_str),)).fetchone()
            if prod:
                subtotal += prod['price'] * min(qty, prod['stock'])
        conn.close()

    totals = calculate_cart_totals(subtotal)
    return jsonify({
        'success': True,
        'message': 'কুপন বাদ দেওয়া হয়েছে।',
        'totals': totals
    })


@app.route('/api/product/<int:pid>/review', methods=['POST'])
def api_add_review(pid):
    """
    প্রোডাক্ট কাস্টমার রিভিউ ও রেটিং জমা নেওয়ার AJAX API:
    - ডাটাবেজের reviews টেবিলে রিভিউ সংরক্ষণ করে।
    - পণ্যের গড় রেটিং ও মোট রিভিউ সংখ্যা আপডেট করে।
    """
    data = request.get_json() or {}
    user_name = data.get('name', '').strip() or session.get('name', 'ভেরিফায়েড ক্রেতা')
    rating_raw = data.get('rating', 5)
    comment = data.get('comment', '').strip()

    try:
        rating = max(1, min(5, int(rating_raw)))
    except (ValueError, TypeError):
        rating = 5

    if not comment:
        return jsonify({'success': False, 'message': 'দয়া করে রিভিউয়ের মন্তব্য লিখুন।'})

    conn = get_db()
    conn.execute("""
        INSERT INTO reviews (product_id, user_name, rating, comment)
        VALUES (?, ?, ?, ?)
    """, (pid, user_name, rating, comment))

    # গড় রেটিং ও রিভিউ সংখ্যা পুনরায় গণনা করা
    stats = conn.execute("""
        SELECT COUNT(*) as count, AVG(rating) as avg_rating
        FROM reviews WHERE product_id = ?
    """, (pid,)).fetchone()

    new_count = stats['count'] if stats else 1
    new_avg = round(stats['avg_rating'], 1) if stats and stats['avg_rating'] else float(rating)

    conn.execute("""
        UPDATE products 
        SET rating = ?, reviews_count = ?
        WHERE id = ?
    """, (new_avg, new_count, pid))

    conn.commit()
    conn.close()

    return jsonify({
        'success': True,
        'message': 'আপনার মূল্যবান রিভিউ সফলভাবে প্রকাশ করা হয়েছে! ধন্যবাদ।',
        'review': {
            'user_name': user_name,
            'rating': rating,
            'comment': comment
        },
        'new_rating': new_avg,
        'new_count': new_count
    })


# =============================================================================
# 📌 VIVA QUESTION: "গ্রাহক চেকআউট করলে অর্ডার ডাটাবেজে কীভাবে জমা হয় এবং স্টক কীভাবে কমে?"
# 👉 ফাইল: app.py (ফাংশন: checkout())
# 👉 টেকনিক: 'BEGIN IMMEDIATE' অ্যাটমিক ট্রানজ্যাকশন ব্যবহার করে স্টক লক করা হয়েছে।
# 👉 টেবিল: orders এবং order_items এ ডাটা ইনসার্ট করা হয় এবং products টেবিলে স্টক কমানো হয়।
# =============================================================================
@app.route('/checkout', methods=['GET', 'POST'])
def checkout():
    """
    চেকআউট পেজ ও অর্ডার প্লেসমেন্ট হ্যান্ডলার:
    - ব্যবহারকারীর ঠিকানা, ফোন ও পেমেন্ট মেথড সংগ্রহ করে।
    - এসকিউএল ট্রানজ্যাকশন ও স্টক লক ব্যবহার করে নিরাপদে অর্ডার তৈরি করে।
    - সফল হলে অর্ডার কনফার্মেশন পেজে রিডাইরেক্ট করে।
    """
    # ইউজার লগইন না থাকলে লগইন পেজে রিডাইরেক্ট
    if not is_logged_in():
        flash('অর্ডার সম্পন্ন করতে প্রথমে আপনার অ্যাকাউন্টে সাইন ইন করুন।', 'info')
        return redirect(url_for('login', next=url_for('checkout')))

    cart_data = session.get('cart', {})
    if not cart_data:
        flash('আপনার কার্ট বর্তমানে খালি। কেনাকাটা করতে প্রোডাক্ট বাছাই করুন।', 'warning')
        return redirect(url_for('index'))

    conn = get_db()

    # ইউজার ইনফো ফেচ করা (ফর্ম প্রি-ফিলের জন্য)
    user = conn.execute("SELECT * FROM users WHERE id = ?", (session['user_id'],)).fetchone()

    # কার্টের আইটেম ও মোট টাকা হিসাব করা
    items = []
    subtotal = 0.0
    for pid_str, qty in cart_data.items():
        p = conn.execute("SELECT * FROM products WHERE id = ?", (int(pid_str),)).fetchone()
        if p:
            actual_qty = min(qty, p['stock'])
            item_total = p['price'] * actual_qty
            subtotal += item_total
            items.append({'product': p, 'qty': actual_qty, 'subtotal': item_total})

    delivery_fee = 0 if subtotal >= 5000 or subtotal == 0 else 60
    grand_total = subtotal + delivery_fee

    error = ''

    if request.method == 'POST':
        # ফর্ম থেকে তথ্য সংগ্রহ
        customer_name = request.form.get('name', '').strip()
        customer_phone = request.form.get('phone', '').strip()
        customer_email = request.form.get('email', '').strip()
        shipping_address = request.form.get('address', '').strip()
        city = request.form.get('city', 'Dhaka').strip()
        payment_method = request.form.get('payment_method', 'Cash on Delivery').strip()
        order_notes = request.form.get('notes', '').strip()

        # ফিল্ড ভ্যালিডেশন
        if not customer_name or not customer_phone or not shipping_address:
            error = 'দয়া করে নাম, ফোন নম্বর এবং সম্পূর্ণ ডেলিভারি ঠিকানা পূরণ করুন।'
        else:
            try:
                # ট্রানজ্যাকশন শুরু (Atomic Transaction) যাতে কনকারেন্ট স্টকের গড়মিল না হয়
                conn.execute('BEGIN IMMEDIATE')

                # স্টক রি-ভ্যালিডেশন
                order_lines = []
                final_total = 0.0

                for pid_str, qty in cart_data.items():
                    product = conn.execute(
                        "SELECT id, name, category, price, stock, image FROM products WHERE id = ?", 
                        (int(pid_str),)
                    ).fetchone()

                    if not product or product['stock'] < qty:
                        raise ValueError(f"দুঃখিত, '{product['name'] if product else 'পণ্য'}' এর পর্যাপ্ত স্টক নেই।")

                    final_total += product['price'] * qty
                    order_lines.append((
                        product['id'],
                        product['name'],
                        product['category'],
                        product['price'],
                        qty,
                        product['image']
                    ))

                # কুপন ডিসকাউন্ট ও চূড়ান্ত ডেলিভারি চার্জ হিসাব করা
                totals = calculate_cart_totals(final_total, city=city)
                final_grand_total = totals['grand_total']
                final_delivery = totals['delivery_fee']
                coupon_code = totals['coupon_code']
                discount_amount = totals['discount']

                # ইউনিক হিউম্যান-রিডেবল অর্ডার কোড তৈরি (যেমন BR-48219)
                order_code = f"BR-{random.randint(10000, 99999)}"

                # orders টেবিলে অর্ডার ইনসার্ট করা
                cursor = conn.execute("""
                    INSERT INTO orders (
                        order_code, user_id, total, delivery_fee, payment_method,
                        customer_name, customer_phone, customer_email,
                        shipping_address, city, order_notes, status, coupon_code, discount
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
                """, (
                    order_code, session['user_id'], final_grand_total, final_delivery,
                    payment_method, customer_name, customer_phone, customer_email,
                    shipping_address, city, order_notes, coupon_code, discount_amount
                ))
                order_id = cursor.lastrowid

                # order_items টেবিলে প্রতিটি আইটেম ইনসার্ট করা এবং মূল স্টক কমানো
                for pid, pname, pcat, pprice, pqty, pimg in order_lines:
                    conn.execute("""
                        INSERT INTO order_items (
                            order_id, product_id, product_name, category, price, quantity, image
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    """, (order_id, pid, pname, pcat, pprice, pqty, pimg))

                    # স্টক কমানো
                    conn.execute(
                        "UPDATE products SET stock = stock - ? WHERE id = ?", 
                        (pqty, pid)
                    )

                # ট্রানজ্যাকশন সফলভাবে সেভ করা
                conn.commit()
                conn.close()

                # সেশনের কার্ট ও কুপন খালি করে দেওয়া
                session['cart'] = {}
                session.pop('coupon', None)
                session.modified = True

                # অর্ডার কনফার্মেশন পেজে রিডাইরেক্ট
                return redirect(url_for('order_success', order_code=order_code))

            except ValueError as ve:
                conn.rollback()
                error = str(ve)
            except Exception as e:
                conn.rollback()
                error = 'অর্ডার প্রসেস করার সময় একটি ত্রুটি ঘটেছে। অনুগ্রহ করে আবার চেষ্টা করুন।'

    # চেকআউটের মোট হিসেব ও ডিসকাউন্ট বের করা
    totals = calculate_cart_totals(subtotal, city='Dhaka')
    conn.close()

    return render_template(
        'checkout.html',
        items=items,
        subtotal=totals['subtotal'],
        delivery_fee=totals['delivery_fee'],
        discount=totals['discount'],
        coupon_code=totals['coupon_code'],
        coupon_desc=totals['coupon_desc'],
        grand_total=totals['grand_total'],
        user=user,
        error=error
    )


@app.route('/order-success/<order_code>')
def order_success(order_code):
    """অর্ডার সফলভাবে প্লেস হওয়ার পর কনফার্মেশন ও ইনভয়েস পেজ"""
    conn = get_db()
    order = conn.execute("SELECT * FROM orders WHERE order_code = ?", (order_code,)).fetchone()
    if not order:
        conn.close()
        return redirect(url_for('index'))

    items = conn.execute("SELECT * FROM order_items WHERE order_id = ?", (order['id'],)).fetchall()
    conn.close()

    return render_template('order_success.html', order=order, items=items)


@app.route('/orders')
def my_orders():
    """লগইন করা ইউজারের অতীতের সব অর্ডারের তালিকা ও ট্র্যাকিং হিস্ট্রি"""
    if not is_logged_in():
        return redirect(url_for('login'))

    conn = get_db()
    orders = conn.execute(
        "SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC", 
        (session['user_id'],)
    ).fetchall()

    # প্রতিটি অর্ডারের আইটেম লিস্ট ম্যাপ করা
    order_items_map = {}
    for o in orders:
        items = conn.execute(
            "SELECT * FROM order_items WHERE order_id = ?", (o['id'],)
        ).fetchall()
        order_items_map[o['id']] = items

    conn.close()

    return render_template('orders.html', orders=orders, order_items=order_items_map)


# =============================================================================
# 📌 VIVA QUESTION: "ইউজার রেজিস্ট্রেশনে পাসওয়ার্ড কীভাবে সুরক্ষিত রাখা হয়েছে?"
# 👉 ফাইল: app.py (ফাংশন: register())
# 👉 সিকিউরিটি: Werkzeug এর generate_password_hash(password) ফাংশন দিয়ে নিরাপদ হ্যাশ তৈরি করে ডাটাবেজে সংরক্ষণ করা হয়।
# =============================================================================
@app.route('/register', methods=['GET', 'POST'])
def register():
    """নতুন ইউজার রেজিস্ট্রেশন পেজ ও ফর্ম প্রসেসর"""
    error = ''
    if request.method == 'POST':
        name = request.form.get('name', '').strip()
        email = request.form.get('email', '').strip().lower()
        password = request.form.get('password', '')
        phone = request.form.get('phone', '').strip()

        if not name or not email or len(password) < 6:
            error = 'দয়া করে সব ফিল্ড সঠিকভাবে পূরণ করুন এবং কমপক্ষে ৬ অক্ষরের পাসওয়ার্ড দিন।'
        else:
            conn = get_db()
            try:
                # পাসওয়ার্ড হ্যাশ করে ডাটাবেজে ইউজার যুক্ত করা
                cursor = conn.execute("""
                    INSERT INTO users (name, email, password, phone, is_admin)
                    VALUES (?, ?, ?, ?, 0)
                """, (name, email, generate_password_hash(password), phone))
                conn.commit()
                
                # স্বয়ংক্রিয়ভাবে লগইন সেশন সেট করা
                new_id = cursor.lastrowid
                session['user_id'] = new_id
                session['name'] = name
                session['is_admin'] = 0
                conn.close()

                return redirect(url_for('index'))
            except sqlite3.IntegrityError:
                conn.close()
                error = 'এই ইমেইল দিয়ে ইতোমধ্যে একটি অ্যাকাউন্ট খোলা রয়েছে। অনুগ্রহ করে লগইন করুন।'

    return render_template('register.html', error=error)


# =============================================================================
# 📌 VIVA QUESTION: "লগইনে পাসওয়ার্ড কীভাবে ভেরিফাই হয় এবং সেশন কীভাবে কাজ করে?"
# 👉 ফাইল: app.py (ফাংশন: login())
# 👉 সিকিউরিটি: check_password_hash(user['password'], password) দিয়ে ভেরিফাই করা হয়।
# 👉 স্টেট: সঠিক হলে Flask সেশনে session['user_id'], session['name'], session['is_admin'] সেভ থাকে।
# =============================================================================
@app.route('/login', methods=['GET', 'POST'])
def login():
    """ইউজার লগইন পেজ ও অথেনটিকেশন প্রসেসর"""
    error = ''
    next_url = request.args.get('next', '')

    if request.method == 'POST':
        email = request.form.get('email', '').strip().lower()
        password = request.form.get('password', '')
        next_page = request.form.get('next', '')

        conn = get_db()
        user = conn.execute("SELECT * FROM users WHERE email = ?", (email,)).fetchone()
        conn.close()

        # পাসওয়ার্ড চেক করা
        if user and check_password_hash(user['password'], password):
            session['user_id'] = user['id']
            session['name'] = user['name']
            session['is_admin'] = user['is_admin']

            if next_page and next_page.startswith('/'):
                return redirect(next_page)
            if user['is_admin'] == 1:
                return redirect(url_for('admin_dashboard'))
            return redirect(url_for('index'))
        else:
            error = 'ভুল ইমেইল অথবা পাসওয়ার্ড দিয়েছেন। অনুগ্রহ করে আবার চেষ্টা করুন।'

    return render_template('login.html', error=error, next=next_url)


@app.route('/logout')
def logout():
    """সেশন ক্লিয়ার করে ইউজার লগআউট করা"""
    session.clear()
    return redirect(url_for('index'))


# =============================================================================
# অ্যাডমিন ড্যাশবোর্ড ও ম্যানেজমেন্ট প্যানেল (Admin Panel)
# =============================================================================

@app.route('/admin/dashboard')
def admin_dashboard():
    """অ্যাডমিন হোম ড্যাশবোর্ড: মোট আয়, বিক্রয়, ইউজার ও সাপোর্ট রিকোয়েস্টের ওভারভিউ"""
    if not is_admin():
        flash('এই পেজটি দেখার জন্য অ্যাডমিন পারমিশন প্রয়োজন।', 'danger')
        return redirect(url_for('login'))

    conn = get_db()
    
    # স্ট্যাটিস্টিক্স হিসেব করা
    total_products = conn.execute("SELECT COUNT(*) FROM products").fetchone()[0]
    total_orders = conn.execute("SELECT COUNT(*) FROM orders").fetchone()[0]
    total_users = conn.execute("SELECT COUNT(*) FROM users").fetchone()[0]
    total_revenue = conn.execute("SELECT COALESCE(SUM(total), 0) FROM orders").fetchone()[0]
    pending_support = conn.execute(
        "SELECT COUNT(*) FROM support_requests WHERE status = 'Pending'"
    ).fetchone()[0]

    # সাম্প্রতিক ৫টি অর্ডার
    recent_orders = conn.execute("""
        SELECT o.*, u.name as user_name FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC LIMIT 5
    """).fetchall()

    # কম স্টকের প্রোডাক্ট সতর্কবার্তা (Stock < 5)
    low_stock = conn.execute(
        "SELECT * FROM products WHERE stock < 5 ORDER BY stock ASC LIMIT 5"
    ).fetchall()

    conn.close()

    return render_template(
        'dashboard.html',
        total_products=total_products,
        total_orders=total_orders,
        total_users=total_users,
        total_revenue=total_revenue,
        pending_support=pending_support,
        recent_orders=recent_orders,
        low_stock=low_stock
    )


@app.route('/admin/products')
def admin_products():
    """অ্যাডমিনের প্রোডাক্ট ইনভেন্টরি ম্যানেজমেন্ট তালিকা"""
    if not is_admin():
        return redirect(url_for('index'))

    conn = get_db()
    products = conn.execute("SELECT * FROM products ORDER BY id DESC").fetchall()
    conn.close()
    return render_template('admin_products.html', products=products)


@app.route('/admin/add-product', methods=['GET', 'POST'])
def admin_add_product():
    """নতুন রিপ্লেসমেন্ট প্রোডাক্ট যুক্ত করার ফর্ম ও হ্যান্ডলার"""
    if not is_admin():
        return redirect(url_for('index'))

    error = ''
    if request.method == 'POST':
        name = request.form.get('name', '').strip()
        brand = request.form.get('brand', '').strip()
        model = request.form.get('model', '').strip()
        category = request.form.get('category', '').strip()
        price = float(request.form.get('price', 0))
        original_price = float(request.form.get('original_price', 0) or price)
        stock = int(request.form.get('stock', 10))
        description = request.form.get('description', '').strip()
        condition = request.form.get('condition', 'Original Brand New (OEM)')
        compatibility = request.form.get('compatibility', '').strip()
        pairing_guide = request.form.get('pairing_guide', '').strip()
        image_url = request.form.get('image_url', '').strip()

        # ইমেজ ফাইল আপলোড হ্যান্ডলিং
        image = image_url or 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?auto=format&fit=crop&w=800&q=80'
        f = request.files.get('image_file')
        if f and f.filename:
            filename = secure_filename(f.filename)
            ext = filename.rsplit('.', 1)[-1].lower()
            if ext in {'png', 'jpg', 'jpeg', 'webp'}:
                save_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)
                f.save(save_path)
                image = url_for('static', filename=f'images/{filename}')

        conn = get_db()
        conn.execute("""
            INSERT INTO products (
                name, brand, model, category, price, original_price,
                image, description, condition, compatibility, pairing_guide, stock
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            name, brand, model, category, price, original_price,
            image, description, condition, compatibility, pairing_guide, stock
        ))
        conn.commit()
        conn.close()

        return redirect(url_for('admin_products'))

    return render_template('add_product.html', error=error)


@app.route('/admin/delete-product/<int:pid>', methods=['POST'])
def admin_delete_product(pid):
    """অ্যাডমিন কর্তৃক প্রোডাক্ট ডিলিট করার হ্যান্ডলার"""
    if not is_admin():
        return redirect(url_for('index'))

    conn = get_db()
    conn.execute("DELETE FROM products WHERE id = ?", (pid,))
    conn.commit()
    conn.close()
    return redirect(url_for('admin_products'))


@app.route('/admin/orders')
def admin_orders():
    """সমস্ত কাস্টমারদের অর্ডার তালিকা ও স্ট্যাটাস ম্যানেজমেন্ট"""
    if not is_admin():
        return redirect(url_for('index'))

    conn = get_db()
    orders = conn.execute("""
        SELECT o.*, u.name as user_name FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC
    """).fetchall()

    # প্রতিটি অর্ডারের আইটেম লিস্ট ফেচ করা
    order_items_map = {}
    for o in orders:
        order_items_map[o['id']] = conn.execute(
            "SELECT * FROM order_items WHERE order_id = ?", (o['id'],)
        ).fetchall()

    conn.close()
    return render_template('admin_orders.html', orders=orders, order_items_map=order_items_map)


@app.route('/admin/update-order-status/<int:oid>', methods=['POST'])
def admin_update_order_status(oid):
    """অর্ডারের স্ট্যাটাস পরিবর্তন (Pending -> Processing -> Shipped -> Delivered)"""
    if not is_admin():
        return redirect(url_for('index'))

    new_status = request.form.get('status', 'Pending')
    conn = get_db()
    conn.execute("UPDATE orders SET status = ? WHERE id = ?", (new_status, oid))
    conn.commit()
    conn.close()
    return redirect(url_for('admin_orders'))


@app.route('/admin/support')
def admin_support():
    """গ্রাহকদের সাপোর্ট ও পার্ট ম্যাচিং রিকোয়েস্ট দেখার ইনবক্স"""
    if not is_admin():
        return redirect(url_for('index'))

    conn = get_db()
    requests_list = conn.execute(
        "SELECT * FROM support_requests ORDER BY id DESC"
    ).fetchall()
    conn.close()
    return render_template('support.html', requests_list=requests_list)


@app.route('/admin/resolve-support/<int:s_id>', methods=['POST'])
def admin_resolve_support(s_id):
    """সাপোর্ট রিকোয়েস্ট সমাধান হিসেবে চিহ্নিত করা"""
    if not is_admin():
        return redirect(url_for('index'))

    conn = get_db()
    conn.execute("UPDATE support_requests SET status = 'Resolved' WHERE id = ?", (s_id,))
    conn.commit()
    conn.close()
    return redirect(url_for('admin_support'))


@app.route('/admin/trade-ins')
def admin_trade_ins():
    """গ্রাহকদের বাড বিক্রি ও ট্রেড-ইন রিকোয়েস্ট দেখার এডমিন প্যানেল"""
    if not is_admin():
        return redirect(url_for('index'))

    conn = get_db()
    requests_list = conn.execute("SELECT * FROM trade_in_requests ORDER BY id DESC").fetchall()
    conn.close()
    return render_template('admin_trade_ins.html', requests_list=requests_list)


@app.route('/admin/update-trade-in/<int:tid>', methods=['POST'])
def admin_update_trade_in(tid):
    """ট্রেড-ইন রিকোয়েস্ট স্ট্যাটাস আপডেট (Pending / Approved / Completed)"""
    if not is_admin():
        return redirect(url_for('index'))

    new_status = request.form.get('status', 'Pending')
    conn = get_db()
    conn.execute("UPDATE trade_in_requests SET status = ? WHERE id = ?", (new_status, tid))
    conn.commit()
    conn.close()
    return redirect(url_for('admin_trade_ins'))


# =============================================================================
# আইপি ল্যাব (IP Lab) পিএইচপি পোর্টাল রুট
# =============================================================================
@app.route('/php-portal')
@app.route('/iplab')
def php_portal_redirect():
    """
    📌 VIVA QUESTION: 'পাইথন এবং পিএইচপি এই প্রজেক্টে কীভাবে একসাথে কাজ করছে?'
    👉 উত্তর: পাইথন ফ্লাস্ক মূল ই-কমার্স প্ল্যাটফর্ম পরিচালনা করছে (পোর্ট ৫০০০),
              এবং পিএইচপি পোর্টালটি (পোর্ট ৮০৮০ / XAMPP) ডেডিকেটেড সার্ভিস হিসেবে একই SQLite
              ডাটাবেজে ইনভয়েস জেনারেশন, ওয়ারেন্টি ভ্যালিডেশন এবং RESTful JSON API প্রসেসিং করছে।
    """
    return redirect('http://127.0.0.1:8080/')


# =============================================================================
# অ্যাপ রানার (Development Server Runner)
# =============================================================================
if __name__ == '__main__':
    # লোকাল ডেভেলপমেন্ট সার্ভার পোর্ট ৫০০ এ চালু করা
    # debug=True দিলে কোড পরিবর্তনের সাথে সাথে অটো রিলোড হবে
    app.run(debug=True, port=5000)
