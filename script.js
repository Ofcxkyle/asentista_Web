/**
 * Asentista Bakery - Interactive Features & Database UI Controller
 * Powers all interactive navigation, search, shopping cart AJAX, product details, lightbox, and toast notifications.
 */

// Global Quick Add to Cart accessible from HTML onclick attributes
window.quickAddToCart = async function(productName, price = 0, image = '', qty = 1) {
    const csrfToken = window.CSRF_TOKEN 
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
        || '';

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_name', productName);
    formData.append('price', price);
    formData.append('image', image);
    formData.append('quantity', qty);
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }

    try {
        const response = await fetch('cart_action.php', {
            method: 'POST',
            body: formData,
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            }
        });
        const result = await response.json();

        if (result.success) {
            updateCartBadge(result.cart_count);
            showToast(`${result.message} <br><a href="cart.php" style="color:var(--color-yellow); text-decoration:underline; font-weight:700;">View Cart & Checkout (${result.cart_count}) →</a>`, 'success');
        } else {
            showToast(result.message || 'Could not add item to cart.', result.out_of_stock ? 'danger' : 'info');
        }
    } catch (e) {
        console.error('Cart add error:', e);
    }
};

window.openDetailByName = function(productName) {
    if (window._openProductByName) {
        window._openProductByName(productName);
    }
};

function updateCartBadge(count) {
    const badge = document.getElementById('cartCountBadge');
    if (badge) {
        badge.textContent = count;
        if (count > 0) {
            badge.classList.add('active');
        } else {
            badge.classList.remove('active');
        }
        badge.classList.remove('bounce');
        void badge.offsetWidth; // Trigger reflow for animation restart
        badge.classList.add('bounce');
    }
}

// Global Toast helper
window.showToast = function(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) return;
    const toast = document.createElement('div');
    toast.className = 'toast-message';
    const icon = type === 'success' ? '✓' : 'ℹ';
    toast.innerHTML = `<span><strong>${icon}</strong> ${message}</span>`;
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-fadeout');
        setTimeout(() => toast.remove(), 350);
    }, 4500);
};

document.addEventListener('DOMContentLoaded', () => {
    // --- Data Store for Bakery Products ---
    const bakeryCatalog = [
        {
            id: 'bread-1',
            name: 'Crunchy Crust',
            category: 'Bread',
            price: '₱35.00',
            raw_price: 35.00,
            desc: 'Golden-baked crust with an airy, soft interior. Perfect for morning dips or artisan sandwiches.',
            image: 'assets/bread-with-appetizing-crunchy-crust-top-view-isolated-on-white-e1656042939392.png'
        },
        {
            id: 'bread-2',
            name: 'Crescent Roll',
            category: 'Bread',
            price: '₱30.00',
            raw_price: 30.00,
            desc: 'Buttery, flaky crescent roll sprinkled with aromatic toasted poppy seeds.',
            image: 'assets/top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.png'
        },
        {
            id: 'bread-3',
            name: 'Round Rye',
            category: 'Bread',
            price: '₱45.00',
            raw_price: 45.00,
            desc: 'Traditional European sourdough rye loaf with rich earthy undertones and dense crumb.',
            image: 'assets/traditional-round-rye-bread-e1656042958429.png'
        },
        {
            id: 'bread-4',
            name: 'Yeast Custard',
            category: 'Bread',
            price: '₱40.00',
            raw_price: 40.00,
            desc: 'Sweet yeast bun filled with silky vanilla custard and spiced caramelized apple.',
            image: 'assets/yeast-bun-with-apple-and-custard-filling-e1656042965940.png'
        },
        {
            id: 'bread-5',
            name: 'Bially Sandwich',
            category: 'Bread',
            price: '₱50.00',
            raw_price: 50.00,
            desc: 'Classic bialy bread roll baked with savory roasted onion and savory seeds.',
            image: 'assets/breads-e1656042972619.png'
        },
        {
            id: 'bread-6',
            name: 'Bun Messes',
            category: 'Bread',
            price: '₱28.00',
            raw_price: 28.00,
            desc: 'Tender, pillowy brioche bun dusted with powdered sugar and natural sweetness.',
            image: 'assets/bun-e1656042983426.png'
        },
        {
            id: 'bread-7',
            name: 'Slice Bread',
            category: 'Bread',
            price: '₱60.00',
            raw_price: 60.00,
            desc: 'Daily sliced sandwich rye loaf made from whole grains and natural levain.',
            image: 'assets/rye-bread-slice-on-a-white-background--e1656042993568.png'
        },
        {
            id: 'bread-8',
            name: 'Bun Roll',
            category: 'Bread',
            price: '₱25.00',
            raw_price: 25.00,
            desc: 'Soft dinner roll with a golden finish, perfect with butter or jam.',
            image: 'assets/bun-1-e1656043014357.png'
        },
        // Menu List items
        { id: 'item-baguette', name: 'Baguette', category: 'Bread', price: '₱25.00', raw_price: 25.00, desc: 'Classic French crusty artisan baguette.', image: 'assets/bread-e1656042861839-pqroqtezjh2g0607d0pphz5ddrx6ppa7b44no9oloo.png' },
        { id: 'item-croissant', name: 'Croissant', category: 'Bread', price: '₱25.00', raw_price: 25.00, desc: 'Laminated, all-butter flaky French pastry.', image: 'assets/top-view-of-crescent-roll-with-poppy-seeds-on-white-background-e1656042946947.png' },
        { id: 'item-sourdough', name: 'Sourdough', category: 'Bread', price: '₱25.00', raw_price: 25.00, desc: 'Slow-fermented artisan sourdough loaf.', image: 'assets/assortment-of-artisan-bread-e1656042887278.png' },
        { id: 'item-ciabatta', name: 'Ciabatta', category: 'Bread', price: '₱25.00', raw_price: 25.00, desc: 'Italian style white bread with olive oil and herbs.', image: 'assets/italian-ciabatta-bread-on-black-slate-with-herbs-and-olives--e1656043199744 (1).png' },
        { id: 'item-brioche', name: 'Brioche', category: 'Bread', price: '₱25.00', raw_price: 25.00, desc: 'Rich golden bread enriched with egg and butter.', image: 'assets/homemade-pumpkin-bread-e1656042901513.png' },
        // Beverages
        { id: 'item-americano', name: 'Americano', category: 'Beverage', price: '₱55.00', raw_price: 55.00, desc: 'Rich double espresso diluted with hot mountain spring water.', image: 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png' },
        { id: 'item-coldbrew', name: 'Cold Brew', category: 'Beverage', price: '₱55.00', raw_price: 55.00, desc: 'Smooth, 18-hour cold steeped single-origin Arabica coffee.', image: 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png' },
        { id: 'item-carbonated', name: 'Carbonated Drink', category: 'Beverage', price: '₱35.00', raw_price: 35.00, desc: 'Refreshing chilled sparkling fruit infusion.', image: 'assets/cheese-platter-with-nuts-honey-and-bread-square-crop-e1656043218344 (1).png' },
        { id: 'item-cortado', name: 'Cortado', category: 'Beverage', price: '₱69.00', raw_price: 69.00, desc: 'Equal parts espresso and warm steamed milk.', image: 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png' },
        { id: 'item-macchiato', name: 'Macchiato', category: 'Beverage', price: '₱69.00', raw_price: 69.00, desc: 'Espresso stained with a dollop of foamed milk.', image: 'assets/banana-bread-slice-of-cake-with-banana-and-blueberries-morning-breakfast-with-coffee-e1656043186302 (1).png' }
    ];

    // --- DOM Elements ---
    const mobileToggleBtn = document.getElementById('mobileToggleBtn');
    const mobileNavDrawer = document.getElementById('mobileNavDrawer');
    const searchModal = document.getElementById('searchModal');
    const bookingModal = document.getElementById('bookingModal');
    const productModal = document.getElementById('productModal');
    const lightboxModal = document.getElementById('lightboxModal');

    // Clean query parameters from URL without displaying popups
    if (window.location.search.includes('auth_success')) {
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
    }

    // --- Modal Open/Close Controls ---
    function openModal(modalEl) {
        if (!modalEl) return;
        modalEl.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalEl) {
        if (!modalEl) return;
        modalEl.classList.remove('active');
        if (!document.querySelector('.modal-backdrop.active') && !document.querySelector('.lightbox-backdrop.active')) {
            document.body.style.overflow = '';
        }
    }

    document.querySelectorAll('.modal-backdrop, .lightbox-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.closest('.modal-close-btn') || e.target.closest('.lightbox-close-btn')) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.active, .lightbox-backdrop.active').forEach(modal => {
                closeModal(modal);
            });
            if (mobileNavDrawer && mobileNavDrawer.classList.contains('open')) {
                mobileNavDrawer.classList.remove('open');
                mobileToggleBtn.classList.remove('active');
            }
        }
    });

    // --- Mobile Menu Toggle ---
    if (mobileToggleBtn && mobileNavDrawer) {
        mobileToggleBtn.addEventListener('click', () => {
            const isOpen = mobileNavDrawer.classList.toggle('open');
            mobileToggleBtn.classList.toggle('active', isOpen);
        });

        mobileNavDrawer.querySelectorAll('.mobile-nav-link').forEach(link => {
            link.addEventListener('click', () => {
                mobileNavDrawer.classList.remove('open');
                mobileToggleBtn.classList.remove('active');
            });
        });
    }

    // --- Smooth Scrolling & Active State (ScrollSpy) ---
    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('section[id], footer[id]');

    function highlightNavOnScroll() {
        const scrollY = window.pageYOffset + 120;
        sections.forEach(section => {
            const sectionHeight = section.offsetHeight;
            const sectionTop = section.offsetTop;
            const sectionId = section.getAttribute('id');

            if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
                navLinks.forEach(link => {
                    link.classList.toggle('active', link.getAttribute('href') === `#${sectionId}`);
                });
            }
        });
    }
    window.addEventListener('scroll', highlightNavOnScroll);

    // --- Downward Arrow in Hero -> Scroll to Fresh Bread ---
    const heroScrollDownBtn = document.getElementById('heroScrollDownBtn');
    if (heroScrollDownBtn) {
        heroScrollDownBtn.addEventListener('click', () => {
            const targetSection = document.getElementById('fresh-bread');
            if (targetSection) {
                targetSection.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    // --- Upward Arrow in Footer -> Scroll to Top ---
    const footerScrollTopBtn = document.getElementById('footerScrollTopBtn');
    if (footerScrollTopBtn) {
        footerScrollTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // --- Brand Logos -> Scroll to Top ---
    document.querySelectorAll('.brand-logo-wrap, .footer-brand-center').forEach(brand => {
        brand.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // --- Organic Product Card -> Open Details Modal ---
    const organicProductCard = document.getElementById('organicProductCard');
    if (organicProductCard) {
        organicProductCard.addEventListener('click', () => {
            openProductDetail({
                name: 'Natural Organic Bread Craft',
                category: 'Organic Special',
                price: 'Daily Harvest',
                raw_price: 60.00,
                desc: 'Slow-crafted daily with 100% certified organic grains, pure mountain spring water, and natural sourdough culture. No artificial preservatives, synthetic additives, or commercial yeast.',
                image: 'assets/AdobeStock_326195507.png'
            });
        });
    }

    // --- Search Feature ---
    const searchTriggerBtn = document.getElementById('searchTriggerBtn');
    const searchInputField = document.getElementById('searchInputField');
    const searchResultsList = document.getElementById('searchResultsList');

    function renderSearchResults(query = '') {
        const filtered = bakeryCatalog.filter(item => {
            const q = query.toLowerCase().trim();
            return item.name.toLowerCase().includes(q) || item.category.toLowerCase().includes(q) || item.desc.toLowerCase().includes(q);
        });

        if (filtered.length === 0) {
            searchResultsList.innerHTML = `<div style="text-align:center; padding: 2rem; color: var(--color-text-muted);">No bakery items found for "${query}". Try "Croissant", "Baguette", or "Brew".</div>`;
            return;
        }

        searchResultsList.innerHTML = filtered.map(item => `
            <div class="search-result-item" data-item-id="${item.id}">
                <div class="search-result-info">
                    <img src="${item.image}" alt="${item.name}" class="search-result-thumb">
                    <div>
                        <div class="search-result-name">${item.name}</div>
                        <div class="search-result-category">${item.category} • ${item.price}</div>
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <button type="button" class="btn-search-cart" onclick="event.stopPropagation(); quickAddToCart('${item.name.replace(/'/g, "\\'")}', ${item.raw_price || 35}, '${item.image.replace(/'/g, "\\'")}')">
                        🛒 + Add to Cart
                    </button>
                    <button type="button" class="btn-search-view" onclick="event.stopPropagation(); window.openDetailByName('${item.name.replace(/'/g, "\\'")}')">
                        View →
                    </button>
                </div>
            </div>
        `).join('');

        searchResultsList.querySelectorAll('.search-result-item').forEach(el => {
            el.addEventListener('click', () => {
                const itemId = el.getAttribute('data-item-id');
                const selectedItem = bakeryCatalog.find(i => i.id === itemId);
                if (selectedItem) {
                    closeModal(searchModal);
                    openProductDetail(selectedItem);
                }
            });
        });
    }

    if (searchTriggerBtn && searchModal) {
        searchTriggerBtn.addEventListener('click', () => {
            openModal(searchModal);
            if (searchInputField) {
                searchInputField.value = '';
                renderSearchResults('');
                setTimeout(() => searchInputField.focus(), 100);
            }
        });

        if (searchInputField) {
            searchInputField.addEventListener('input', (e) => {
                renderSearchResults(e.target.value);
            });
        }
    }

    // Global shortcut '/' to open search
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            e.preventDefault();
            if (searchTriggerBtn) searchTriggerBtn.click();
        }
    });

    // --- Product Detail Modal ---
    let currentModalProduct = null;

    function openProductDetail(product) {
        currentModalProduct = product;
        const imgEl = document.getElementById('productDetailImg');
        const titleEl = document.getElementById('productDetailTitle');
        const priceEl = document.getElementById('productDetailPrice');
        const descEl = document.getElementById('productDetailDesc');
        const stockEl = document.getElementById('productDetailStock');
        const orderBtn = document.getElementById('productDetailOrderBtn');
        const cartBtn = document.getElementById('productModalAddToCartBtn');

        if (imgEl) imgEl.src = product.image;
        if (titleEl) titleEl.textContent = product.name;
        if (priceEl) priceEl.textContent = product.price;
        if (descEl) descEl.textContent = product.desc;

        // Stock and availability
        const stock = (product.stock !== undefined) ? parseInt(product.stock) : 999;
        const isOutOfStock = (stock <= 0);

        if (stockEl) {
            if (isOutOfStock) {
                stockEl.innerHTML = '<span style="color:#D32F2F; background:rgba(211,47,47,0.1); padding:4px 10px; border-radius:4px; display:inline-block;">❌ Out of Stock / Unavailable</span>';
            } else if (stock <= 4) {
                stockEl.innerHTML = `<span style="color:#E65100; background:rgba(230,81,0,0.1); padding:4px 10px; border-radius:4px; display:inline-block;">⚠️ Low Stock: Only ${stock} units left!</span>`;
            } else {
                stockEl.innerHTML = `<span style="color:#2E7D32; background:rgba(46,125,50,0.1); padding:4px 10px; border-radius:4px; display:inline-block;">✓ In Stock (${stock} available)</span>`;
            }
        }

        if (cartBtn) {
            if (isOutOfStock) {
                cartBtn.disabled = true;
                cartBtn.style.opacity = '0.5';
                cartBtn.style.cursor = 'not-allowed';
                cartBtn.textContent = 'Out of Stock';
            } else {
                cartBtn.disabled = false;
                cartBtn.style.opacity = '1';
                cartBtn.style.cursor = 'pointer';
                cartBtn.textContent = '🛒 Add to Cart Now';
                cartBtn.onclick = () => {
                    quickAddToCart(product.name, product.raw_price || 35, product.image);
                };
            }
        }

        if (orderBtn) {
            if (isOutOfStock) {
                orderBtn.disabled = true;
                orderBtn.style.opacity = '0.5';
                orderBtn.style.cursor = 'not-allowed';
                orderBtn.textContent = 'Unavailable for Booking';
            } else {
                orderBtn.disabled = false;
                orderBtn.style.opacity = '1';
                orderBtn.style.cursor = 'pointer';
                orderBtn.textContent = 'Direct Book / Custom Reservation →';
                orderBtn.onclick = () => {
                    closeModal(productModal);
                    openBookingWithItem(product.name);
                };
            }
        }

        openModal(productModal);
    }

    window._openProductByName = function(name) {
        let found = null;
        if (window.SERVER_PRODUCTS && Array.isArray(window.SERVER_PRODUCTS)) {
            const p = window.SERVER_PRODUCTS.find(i => i.name.toLowerCase() === name.toLowerCase());
            if (p) {
                found = {
                    name: p.name,
                    category: p.category,
                    price: '₱' + parseFloat(p.price).toFixed(2),
                    raw_price: parseFloat(p.price),
                    stock: parseInt(p.stock),
                    desc: p.description,
                    image: p.image
                };
            }
        }
        if (!found) {
            found = bakeryCatalog.find(i => i.name.toLowerCase() === name.toLowerCase()) || {
                name: name,
                category: 'Artisan Bread',
                price: '₱35.00',
                raw_price: 35.00,
                stock: 15,
                desc: 'Handcrafted with natural ingredients and baked fresh daily in our brick ovens.',
                image: 'assets/breads-e1656042972619.png'
            };
        }
        openProductDetail(found);
    };

    // --- Booking / Reservation Modal Connected to Database ---
    const bookNowBtns = document.querySelectorAll('.btn-book-now, #bookNowHeroBtn, .trigger-booking-modal');
    const bookingForm = document.getElementById('bakeryBookingForm');
    const bookingItemSelect = document.getElementById('bookingItemSelect');

    function populateBookingSelect() {
        if (!bookingItemSelect) return;
        const currentVal = bookingItemSelect.value;
        const items = (window.SERVER_PRODUCTS && window.SERVER_PRODUCTS.length) ? window.SERVER_PRODUCTS : bakeryCatalog;
        bookingItemSelect.innerHTML = '<option value="">-- Select Favorite Bread or Beverage --</option>' +
            items.map(item => {
                const stock = (item.stock !== undefined) ? parseInt(item.stock) : 999;
                const isOut = stock <= 0;
                const priceStr = item.price.toString().startsWith('₱') ? item.price : '₱' + parseFloat(item.price).toFixed(2);
                const label = isOut ? `${item.name} (${priceStr}) — [OUT OF STOCK]` : `${item.name} (${priceStr}) — ${stock} in stock`;
                return `<option value="${item.name}" ${isOut ? 'disabled' : ''}>${label}</option>`;
            }).join('');
        if (currentVal) bookingItemSelect.value = currentVal;
    }
    populateBookingSelect();

    function openBookingWithItem(itemName = '') {
        if (!window.isLoggedIn) {
            showToast('<strong>Account Required:</strong> Guests can explore the menu, but please sign in or register to place bakery orders and reservations.<br><a href="auth.php?redirect=index.php&msg=login_to_order" style="color:var(--color-yellow); font-weight:700; text-decoration:underline;">Click here to Sign In or Create Account →</a>', 'info');
            setTimeout(() => {
                window.location.href = 'auth.php?redirect=index.php&msg=login_to_order';
            }, 1800);
            return;
        }

        populateBookingSelect();
        if (bookingItemSelect && itemName) {
            const matchingOpt = Array.from(bookingItemSelect.options).find(opt => opt.value.toLowerCase().includes(itemName.toLowerCase()));
            if (matchingOpt) {
                bookingItemSelect.value = matchingOpt.value;
            }
        }
        const dateInput = document.getElementById('bookingDate');
        if (dateInput && !dateInput.value) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
            dateInput.min = today;
        }
        openModal(bookingModal);
    }

    bookNowBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (btn.tagName === 'A') return;
            e.preventDefault();
            openBookingWithItem();
        });
    });

    if (bookingForm) {
        bookingForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!window.isLoggedIn) {
                showToast('<strong>Account Required:</strong> Please sign in to submit your reservation.', 'info');
                setTimeout(() => { window.location.href = 'auth.php?redirect=index.php&msg=login_to_order'; }, 1200);
                return;
            }

            const submitBtn = bookingForm.querySelector('.btn-submit-modal');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span>Saving to Database...</span>';
            submitBtn.disabled = true;

            const formData = new FormData(bookingForm);
            const token = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            if (token && !formData.get('csrf_token')) {
                formData.append('csrf_token', token);
            }

            try {
                const response = await fetch('order_process.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': token
                    }
                });

                const result = await response.json();

                if (result.require_auth) {
                    showToast(`<strong>Account Required:</strong> ${result.message}`, 'info');
                    setTimeout(() => { window.location.href = result.redirect || 'auth.php?msg=login_to_order'; }, 1500);
                    return;
                }

                if (result.success) {
                    closeModal(bookingModal);
                    bookingForm.reset();
                    showToast(`<strong>Order #${result.order_id} Received!</strong> ${result.message} <br><a href="dashboard.php" style="color:var(--color-yellow); text-decoration:underline;">View in Orders Portal →</a>`, 'success');
                } else {
                    alert(result.message || 'There was an error saving your order.');
                }
            } catch (err) {
                bookingForm.submit();
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    }

    // --- Instagram Lightbox Gallery ---
    const instaThumbs = document.querySelectorAll('.insta-thumb-wrap');
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxPrevBtn = document.getElementById('lightboxPrevBtn');
    const lightboxNextBtn = document.getElementById('lightboxNextBtn');

    let currentInstaIndex = 0;
    const instaImages = Array.from(instaThumbs).map(wrap => wrap.querySelector('img').src);

    function showLightboxImage(index) {
        if (!instaImages.length) return;
        currentInstaIndex = (index + instaImages.length) % instaImages.length;
        if (lightboxImg) {
            lightboxImg.src = instaImages[currentInstaIndex];
        }
    }

    instaThumbs.forEach((wrap, idx) => {
        wrap.addEventListener('click', () => {
            showLightboxImage(idx);
            openModal(lightboxModal);
        });
    });

    if (lightboxPrevBtn) {
        lightboxPrevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            showLightboxImage(currentInstaIndex - 1);
        });
    }

    if (lightboxNextBtn) {
        lightboxNextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            showLightboxImage(currentInstaIndex + 1);
        });
    }

    document.addEventListener('keydown', (e) => {
        if (lightboxModal && lightboxModal.classList.contains('active')) {
            if (e.key === 'ArrowLeft') showLightboxImage(currentInstaIndex - 1);
            if (e.key === 'ArrowRight') showLightboxImage(currentInstaIndex + 1);
        }
    });

    // --- Social Links Notice ---
    document.querySelectorAll('.social-box-icon').forEach(icon => {
        icon.addEventListener('click', () => {
            const platform = icon.getAttribute('data-platform') || 'Social Media';
            showToast(`Opening Asentista Bakery on ${platform}...`, 'info');
        });
    });
});
