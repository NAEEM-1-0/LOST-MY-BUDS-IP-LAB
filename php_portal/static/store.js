/**
 * =============================================================================
 * প্রজেক্ট নাম: BudsRecover (ইয়ারবাড রিপ্লেসমেন্ট ই-কমার্স প্ল্যাটফর্ম)
 * ফাইল: static/store.js
 * বিবরণ: ক্লায়েন্ট-সাইড জাভাস্ক্রিপ্ট - স্লাইড-আউট সাইড কার্ট ড্রয়ার (AJAX Mini-Cart),
 *       কুইক ভিউ মডাল, বাড ফাইন্ডার উইজার্ড, টোস্ট নোটিফিকেশন, মোবাইল ড্রয়ার ও ইউজার মেনু।
 * গ্রুপ প্রজেক্টের সুবিধার জন্য প্রতিটি ফাংশন ও ইভেন্ট লিসেনারে বিস্তারিত বাংলা কমেন্ট রয়েছে।
 * =============================================================================
 */

// ব্রাউজারে সম্পূর্ণ DOM লোড হওয়ার পর স্ক্রিপ্ট রান করানো
document.addEventListener('DOMContentLoaded', () => {
    
    // ১. গ্লোবাল DOM এলিমেন্টসমূহ নির্বাচন করা
    const cartDrawer = document.getElementById('cartDrawer');
    const cartBackdrop = document.getElementById('cartBackdrop');
    const openCartBtn = document.getElementById('openCartBtn');
    const closeCartBtn = document.getElementById('closeCartBtn');
    const mobileBottomCartBtn = document.getElementById('mobileBottomCartBtn');
    
    const quickViewModal = document.getElementById('quickViewModal');
    const quickViewBackdrop = document.getElementById('quickViewBackdrop');
    const closeQuickViewBtn = document.getElementById('closeQuickViewBtn');
    const quickViewContent = document.getElementById('quickViewContent');
    
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileNavDrawer = document.getElementById('mobileNavDrawer');

    // =============================================================================
    // ২. সাইড কার্ট ড্রয়ার ওপেন ও ক্লোজ ফাংশনসমূহ (Cart Drawer Controls)
    // =============================================================================

    /**
     * =========================================================================
     * 📌 VIVA QUESTION: "কার্ট ড্রয়ার ওপেন করার জাভাস্ক্রিপ্ট কোড কোথায়?"
     * 👉 ফাইল: static/store.js (ফাংশন: openCart())
     * 👉 কাজ: কার্ট ড্রয়ার ও ব্যাকড্রপকে 'open' ক্লাস যুক্ত করে ভিজিবল করে 
     *         এবং loadCartState() এর মাধ্যমে ব্যাকএন্ড থেকে লাইভ আইটেম সাজিয়ে দেয়।
     * =========================================================================
     */
    function openCart() {
        if (cartDrawer && cartBackdrop) {
            cartDrawer.classList.add('open');
            cartBackdrop.classList.add('open');
            document.body.style.overflow = 'hidden'; // ব্যাকগ্রাউন্ড স্ক্রল বন্ধ রাখা
            loadCartState(); // কার্টের আইটেমগুলো লোড করা
        }
    }

    /**
     * কার্ট ড্রয়ার বন্ধ করার ফাংশন
     */
    function closeCart() {
        if (cartDrawer && cartBackdrop) {
            cartDrawer.classList.remove('open');
            cartBackdrop.classList.remove('open');
            document.body.style.overflow = ''; // ব্যাকগ্রাউন্ড স্ক্রল স্বাভাবিক করা
        }
    }

    // হেডার ও মোবাইল বটম কার্ট বাটনে ক্লিক ইভেন্ট যোগ করা
    if (openCartBtn) openCartBtn.addEventListener('click', openCart);
    if (mobileBottomCartBtn) mobileBottomCartBtn.addEventListener('click', openCart);
    if (closeCartBtn) closeCartBtn.addEventListener('click', closeCart);
    if (cartBackdrop) cartBackdrop.addEventListener('click', closeCart);

    // কিবোর্ডের Escape বাটন চাপলে ড্রয়ার ও মডাল বন্ধ হবে
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCart();
            closeQuickView();
        }
    });


    // =============================================================================
    // ৩. রিয়েল-টাইম AJAX কার্ট ফেচিং ও রেন্ডারিং (Mini-Cart AJAX Engine)
    // =============================================================================

    /**
     * সার্ভার থেকে কার্টের বর্তমান তথ্য এনে ড্রয়ারে সাজানোর ফাংশন
     */
    async function loadCartState() {
        const itemsContainer = document.getElementById('drawerCartItems');
        const countHeader = document.getElementById('drawerCartCount');
        const subtotalEl = document.getElementById('drawerSubtotal');
        const progressBar = document.getElementById('deliveryProgressBar');
        const progressMsg = document.getElementById('freeDeliveryMessage');
        const footerEl = document.getElementById('drawerCartFooter');

        if (!itemsContainer) return;

        try {
            const res = await fetch('/api/cart/state');
            const data = await res.json();

            // ব্যাজ কাউন্ট আপডেট করা
            updateAllCartBadges(data.total_count);

            if (countHeader) countHeader.innerText = `${data.total_count} টি আইটেম`;
            if (subtotalEl) subtotalEl.innerText = `৳${Math.round(data.subtotal)}`;

            // ফ্রি ডেলিভারি প্রোগ্রেস বার হিসাব করা (৳৫০০০ এ ফ্রি)
            const freeThreshold = 5000;
            const percentage = Math.min(100, Math.round((data.subtotal / freeThreshold) * 100));
            if (progressBar) progressBar.style.width = `${percentage}%`;

            if (progressMsg) {
                if (data.subtotal >= freeThreshold) {
                    progressMsg.innerHTML = '<span>🎉</span> অভিনন্দন! আপনার অর্ডারে <strong>ফ্রি ডেলিভারি</strong> নিশ্চিত!';
                } else if (data.subtotal > 0) {
                    const remaining = freeThreshold - data.subtotal;
                    progressMsg.innerHTML = `<span>🚚</span> আর মাত্র <strong>৳${Math.round(remaining)}</strong> টাকার অর্ডারে ফ্রি ডেলিভারি!`;
                } else {
                    progressMsg.innerHTML = '<span>🚚</span> ৳৫,০০০ বা তার বেশি অর্ডারে ফ্রি ডেলিভারি!';
                }
            }

            // যদি কার্ট খালি থাকে
            if (!data.items || data.items.length === 0) {
                itemsContainer.innerHTML = `
                    <div class="empty-drawer-box">
                        <div class="empty-drawer-icon">🛍️</div>
                        <h4>আপনার ব্যাগ বর্তমানে খালি</h4>
                        <p>হারিয়ে যাওয়া ইয়ারবাডের পার্ট খুঁজে নিতে আমাদের ক্যাটালগ দেখুন।</p>
                        <a href="/#shop" class="btn btn-primary btn-block" onclick="document.getElementById('cartBackdrop').click();">
                            পার্টস দেখুন ➔
                        </a>
                    </div>
                `;
                if (footerEl) footerEl.style.display = 'none';
                return;
            }

            if (footerEl) footerEl.style.display = 'block';

            // কার্টের আইটেমগুলো এইচটিএমএল এ জেনারেট করা
            itemsContainer.innerHTML = data.items.map(item => `
                <div class="drawer-item-row" data-pid="${item.id}">
                    <div class="drawer-thumb-box">
                        <img src="${item.image}" alt="${item.name}" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=800&q=80';">
                    </div>
                    <div class="drawer-item-details">
                        <span class="drawer-item-cat">${item.brand} · ${item.category}</span>
                        <h4 class="drawer-item-title">${item.name}</h4>
                        <div class="drawer-item-pricing">
                            <span class="drawer-unit-price">৳${Math.round(item.price)}</span>
                        </div>
                        <div class="drawer-qty-stepper">
                            <button type="button" class="drawer-qty-btn" onclick="updateDrawerItemQty(${item.id}, -1)">−</button>
                            <span class="drawer-qty-num">${item.qty}</span>
                            <button type="button" class="drawer-qty-btn" onclick="updateDrawerItemQty(${item.id}, 1)">+</button>
                        </div>
                    </div>
                    <div class="drawer-item-actions">
                        <strong class="drawer-row-subtotal">৳${Math.round(item.subtotal)}</strong>
                        <button type="button" class="drawer-remove-btn" onclick="removeDrawerItem(${item.id})" title="ব্যাগ থেকে বাদ দিন">
                            ✕
                        </button>
                    </div>
                </div>
            `).join('');

        } catch (err) {
            console.error('Error loading cart state:', err);
            itemsContainer.innerHTML = '<p class="error-msg text-center">কার্ট লোড করতে সমস্যা হয়েছে।</p>';
        }
    }

    /**
     * সকল হেডারের কার্ট কাউন্টার ব্যাজ আপডেট করার হেল্পার
     */
    function updateAllCartBadges(count) {
        const headerCount = document.getElementById('headerCartCount');
        const mobileCount = document.getElementById('mobileCartCount');
        if (headerCount) headerCount.innerText = count;
        if (mobileCount) mobileCount.innerText = count;
    }


    // =============================================================================
    // ৪. প্রোডাক্ট কার্ড থেকে AJAX Add-to-Cart হ্যান্ডলার
    // =============================================================================

    /**
     * =========================================================================
     * 📌 VIVA QUESTION: "প্রোডাক্ট কার্ডের কার্টে যোগ বাটন চাপলে জাভাস্ক্রিপ্ট কীভাবে ব্যাকএন্ডে রিকোয়েস্ট পাঠায়?"
     * 👉 ফাইল: static/store.js (ইভেন্ট ডেলিগেশন: '.ajax-add-to-cart-btn')
     * 👉 কাজ: বাটনের data-pid নিয়ে fetch(`/api/cart/add/${pid}`, { method: 'POST' }) কল করে,
     *         টোস্ট মেসেজ দেখায় এবং openCart() দিয়ে ড্রয়ার ওপেন করে।
     * =========================================================================
     */
    document.addEventListener('click', async (e) => {
        const addBtn = e.target.closest('.ajax-add-to-cart-btn');
        if (!addBtn) return;

        e.preventDefault();
        const pid = addBtn.getAttribute('data-pid');
        if (!pid) return;

        // বাটনে লোডিং এনিমেশন দেওয়া
        const originalHtml = addBtn.innerHTML;
        addBtn.disabled = true;
        addBtn.innerHTML = '<span>যোগ হচ্ছে...</span>';

        try {
            const res = await fetch(`/api/cart/add/${pid}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await res.json();

            if (data.success) {
                // টোস্ট নোটিফিকেশন প্রদর্শন করা
                showToast(data.message, 'success');
                // কার্ট ব্যাজ আপডেট
                updateAllCartBadges(data.total_count);
                // স্বয়ংক্রিয়ভাবে সাইড ড্রয়ার খুলে দেওয়া
                openCart();
            } else {
                showToast(data.message || 'কার্টে যোগ করা সম্ভব হয়নি।', 'warning');
            }
        } catch (err) {
            console.error('Add to cart error:', err);
            showToast('নেটওয়ার্ক সমস্যা। আবার চেষ্টা করুন।', 'danger');
        } finally {
            addBtn.disabled = false;
            addBtn.innerHTML = originalHtml;
        }
    });

    /**
     * ড্রয়ারের ভেতরে + বা - চাপলে পরিমাণ আপডেট করার গ্লোবাল ফাংশন
     */
    window.updateDrawerItemQty = async function(pid, delta) {
        try {
            const res = await fetch(`/api/cart/update/${pid}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ delta })
            });
            const data = await res.json();
            if (data.success) {
                loadCartState(); // ড্রয়ার রিফ্রেশ
            } else {
                showToast(data.message, 'warning');
            }
        } catch (err) {
            console.error('Update qty error:', err);
        }
    };

    /**
     * ড্রয়ার থেকে আইটেম মুছে ফেলার গ্লোবাল ফাংশন
     */
    window.removeDrawerItem = async function(pid) {
        try {
            const res = await fetch(`/api/cart/remove/${pid}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                showToast('আইটেমটি ব্যাগ থেকে সরানো হয়েছে।', 'info');
                loadCartState();
            }
        } catch (err) {
            console.error('Remove item error:', err);
        }
    };


    // =============================================================================
    // ৫. কুইক ভিউ মডাল কন্ট্রোলার (Quick View Modal)
    // =============================================================================

    /**
     * প্রোডাক্ট কার্ডের চোখ আইকনে ক্লিক করলে কুইক ভিউ পপআপ ওপেন করা
     */
    document.addEventListener('click', async (e) => {
        const qvBtn = e.target.closest('.quickview-btn');
        if (!qvBtn) return;

        const pid = qvBtn.getAttribute('data-pid');
        if (!pid) return;

        openQuickViewModal();
        if (quickViewContent) {
            quickViewContent.innerHTML = `
                <div class="modal-loading-box">
                    <div class="spinner"></div>
                    <p>প্রোডাক্টের তথ্য লোড হচ্ছে...</p>
                </div>
            `;
        }

        try {
            const res = await fetch(`/api/product/${pid}`);
            const data = await res.json();

            if (data.success && quickViewContent) {
                const p = data.product;
                quickViewContent.innerHTML = `
                    <div class="modal-img-col">
                        <img src="${p.image}" alt="${p.name}" class="modal-product-img" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=800&q=80';">
                        <span class="badge part-badge ${p.category.toLowerCase().replace(' ', '-')}">${p.category}</span>
                    </div>
                    <div class="modal-info-col">
                        <span class="modal-brand-tag">${p.brand} · ${p.model}</span>
                        <h2 class="modal-title">${p.name}</h2>
                        
                        <div class="modal-pricing-row">
                            <span class="modal-price">৳${Math.round(p.price)}</span>
                            ${p.original_price && p.original_price > p.price ? `<span class="modal-orig-price">৳${Math.round(p.original_price)}</span>` : ''}
                            <span class="stock-pill in-stock mini">স্টকে আছে (${p.stock} টি)</span>
                        </div>

                        <p class="modal-desc">${p.description || 'আসল OEM রিপ্লেসমেন্ট পার্ট।'}</p>

                        <div class="modal-compatibility-alert">
                            <strong>⚠️ কমপ্যাটিবিলিটি:</strong> ${p.compatibility || 'আপনার বিদ্যমান সেটের মডেল মিলিয়ে নিন।'}
                        </div>

                        <div class="modal-actions-row">
                            <button type="button" class="btn btn-primary btn-large ajax-add-to-cart-btn" data-pid="${p.id}" onclick="closeQuickView();">
                                + ব্যাগে যোগ করুন
                            </button>
                            <a href="/product/${p.id}" class="btn btn-outline btn-large">
                                পূর্ণাঙ্গ পেজ দেখুন ➔
                            </a>
                        </div>
                    </div>
                `;
            }
        } catch (err) {
            console.error('Quick view error:', err);
            if (quickViewContent) {
                quickViewContent.innerHTML = '<p class="error-msg">তথ্য লোড করতে ব্যর্থ হয়েছে।</p>';
            }
        }
    });

    function openQuickViewModal() {
        if (quickViewModal && quickViewBackdrop) {
            quickViewModal.classList.add('open');
            quickViewBackdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeQuickView() {
        if (quickViewModal && quickViewBackdrop) {
            quickViewModal.classList.remove('open');
            quickViewBackdrop.classList.remove('open');
            document.body.style.overflow = '';
        }
    }

    if (closeQuickViewBtn) closeQuickViewBtn.addEventListener('click', closeQuickView);
    if (quickViewBackdrop) quickViewBackdrop.addEventListener('click', closeQuickView);


    // =============================================================================
    // ৬. ইন্টারেক্টিভ বাড ফাইন্ডার উইজার্ড স্ক্রিপ্ট (Finder Wizard)
    // =============================================================================

    const brandPills = document.querySelectorAll('#brandPills .brand-pill');
    const finderBrandInput = document.getElementById('finderBrandInput');
    const finderModelSelect = document.getElementById('finderModelSelect');

    const partPills = document.querySelectorAll('#partPills .part-pill');
    const finderCategoryInput = document.getElementById('finderCategoryInput');

    // ব্র্যান্ড পিল ক্লিক করলে সিলেক্ট করা এবং মডেল ড্রপডাউন ফিল্টার করা
    if (brandPills.length > 0 && finderBrandInput) {
        brandPills.forEach(pill => {
            pill.addEventListener('click', () => {
                const selectedBrand = pill.getAttribute('data-brand');
                
                // আগের সিলেক্টেড সরানো
                brandPills.forEach(p => p.classList.remove('selected'));
                pill.classList.add('selected');
                finderBrandInput.value = selectedBrand;

                // মডেল সিলেক্টরে ফিল্টার করা
                if (finderModelSelect) {
                    Array.from(finderModelSelect.options).forEach(opt => {
                        if (!opt.value) {
                            opt.style.display = '';
                            return;
                        }
                        const optBrand = opt.getAttribute('data-brand');
                        if (optBrand === selectedBrand) {
                            opt.style.display = '';
                        } else {
                            opt.style.display = 'none';
                        }
                    });
                    finderModelSelect.value = ''; // রিসেট
                }
            });
        });
    }

    // পার্ট টাইপ (Left/Right/Case) পিল সিলেকশন
    if (partPills.length > 0 && finderCategoryInput) {
        partPills.forEach(pill => {
            pill.addEventListener('click', () => {
                partPills.forEach(p => p.classList.remove('selected'));
                pill.classList.add('selected');
                finderCategoryInput.value = pill.getAttribute('data-category');
            });
        });
    }


    // =============================================================================
    // ৭. ফ্লোটিং টোস্ট অ্যালার্ট সিস্টেম (Toast Notification Engine)
    // =============================================================================

    /**
     * স্ক্রিনের নিচে সুন্দর টোস্ট নোটিফিকেশন প্রদর্শন করার ফাংশন
     * @param {string} message - প্রদর্শনযোগ্য মেসেজ
     * @param {string} type - 'success', 'warning', 'info', 'danger'
     */
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast-item toast-${type}`;
        
        let icon = '✓';
        if (type === 'warning') icon = '⚠️';
        if (type === 'danger') icon = '✕';
        if (type === 'info') icon = 'ℹ️';

        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close-btn" onclick="this.parentElement.remove()">✕</button>
        `;

        container.appendChild(toast);

        // ৩.৫ সেকেন্ড পর টোস্টটি ফেড আউট হয়ে মুছে যাবে
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }
    window.showToast = showToast; // গ্লোবালি অ্যাক্সেসিবল করা


    // =============================================================================
    // ৮. ইউজার ড্রপডাউন মেনু ও মোবাইল মেনু টগল
    // =============================================================================

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        // বাইরে ক্লিক করলে ড্রপডাউন বন্ধ হওয়া
        document.addEventListener('click', (e) => {
            if (!userDropdown.contains(e.target) && e.target !== userMenuBtn) {
                userDropdown.classList.remove('show');
            }
        });
    }

    // =============================================================================
    // ৯. ডার্ক ও লাইট থিম টগল ইঞ্জিন (Dark / Light Mode Toggle)
    // =============================================================================

    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeIcon');
    const savedTheme = localStorage.getItem('budsrecover_theme') || 'dark';

    // সংরক্ষিত থিম অ্যাপ্লাই করা
    if (savedTheme === 'light') {
        document.body.classList.add('light-theme');
        if (themeIcon) themeIcon.innerText = '☀️';
    } else {
        document.body.classList.remove('light-theme');
        if (themeIcon) themeIcon.innerText = '🌙';
    }

    // বাটনে ক্লিক করলে থিম পরিবর্তন
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const isLight = document.body.classList.toggle('light-theme');
            if (isLight) {
                if (themeIcon) themeIcon.innerText = '☀️';
                localStorage.setItem('budsrecover_theme', 'light');
                showToast('লাইট মোড সক্রিয় করা হয়েছে', 'info');
            } else {
                if (themeIcon) themeIcon.innerText = '🌙';
                localStorage.setItem('budsrecover_theme', 'dark');
                showToast('ডার্ক মোড সক্রিয় করা হয়েছে', 'info');
            }
        });
    }

});
