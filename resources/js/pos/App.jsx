import React, { useState, useCallback, useEffect } from 'react';
import ProductSearch from './components/ProductSearch';
import CartPanel from './components/CartPanel';
import PaymentModal from './components/PaymentModal';
import SaleComplete from './components/SaleComplete';
import SalesHistory from './components/SalesHistory';
import PosReport from './components/PosReport';
import ClassSelector from './components/ClassSelector';
import VariantSelector from './components/VariantSelector';
import useMediaQuery from './hooks/useMediaQuery';

const VIEWS = { POS: 'pos', HISTORY: 'history', REPORT: 'report' };

const TABS = [
    { key: VIEWS.POS, label: 'POS', icon: (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
    )},
    { key: VIEWS.HISTORY, label: 'Sales History', icon: (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    )},
    { key: VIEWS.REPORT, label: 'Report', icon: (
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
    )},
];

export default function App() {
    const config = window.posConfig || {};
    const isMobile = useMediaQuery('(max-width: 1023px)');
    const isSmallMobile = useMediaQuery('(max-width: 639px)');
    const isSmallDesktop = useMediaQuery('(min-width: 1024px) and (max-width: 1365px)');
    const isLargeDesktop = useMediaQuery('(min-width: 1920px)');

    const [view, setView] = useState(() => {
        const hash = window.location.hash.replace('#', '');
        return Object.values(VIEWS).includes(hash) ? hash : VIEWS.POS;
    });

    useEffect(() => {
        window.location.hash = view;
    }, [view]);
    const [cart, setCart] = useState([]);
    const [customer, setCustomer] = useState(null);
    const [showPayment, setShowPayment] = useState(false);
    const [completedSale, setCompletedSale] = useState(null);
    const [classSelector, setClassSelector] = useState(null);
    const [variantSelector, setVariantSelector] = useState(null);
    const [discount, setDiscount] = useState({ amount: 0, type: 'fixed' });
    const [postage, setPostage] = useState(0);
    const [showCartDrawer, setShowCartDrawer] = useState(false);

    const cartItemCount = cart.reduce((sum, item) => sum + item.quantity, 0);

    const addToCart = useCallback((item) => {
        setCart(prev => {
            const key = `${item.type}-${item.id}-${item.variantId || ''}-${item.classId || ''}`;
            const existing = prev.find(c => c.key === key);
            if (existing) {
                return prev.map(c => c.key === key
                    ? { ...c, quantity: c.quantity + 1, totalPrice: (c.quantity + 1) * c.unitPrice }
                    : c
                );
            }
            return [...prev, { ...item, key, quantity: 1, totalPrice: item.unitPrice }];
        });
    }, []);

    const updateQuantity = useCallback((key, quantity) => {
        if (quantity <= 0) {
            setCart(prev => prev.filter(c => c.key !== key));
        } else {
            setCart(prev => prev.map(c => c.key === key
                ? { ...c, quantity, totalPrice: quantity * c.unitPrice }
                : c
            ));
        }
    }, []);

    const removeFromCart = useCallback((key) => {
        setCart(prev => prev.filter(c => c.key !== key));
    }, []);

    const clearCart = useCallback(() => {
        setCart([]);
        setCustomer(null);
        setDiscount({ amount: 0, type: 'fixed' });
        setPostage(0);
    }, []);

    const handleProductClick = useCallback((product) => {
        if (product.variants && product.variants.length > 0) {
            setVariantSelector(product);
        } else {
            addToCart({
                type: 'product',
                id: product.id,
                name: product.name,
                variantId: null,
                variantName: null,
                classId: null,
                unitPrice: parseFloat(product.base_price),
                sku: product.sku || null,
                image: product.primary_image?.url || null,
            });
        }
    }, [addToCart]);

    const handlePackageClick = useCallback((pkg) => {
        addToCart({
            type: 'package',
            id: pkg.id,
            name: pkg.name,
            variantId: null,
            variantName: null,
            classId: null,
            unitPrice: parseFloat(pkg.price),
            sku: null,
            image: null,
        });
    }, [addToCart]);

    const handleCourseClick = useCallback((course) => {
        setClassSelector(course);
    }, []);

    const handleClassSelected = useCallback((course, classItem) => {
        addToCart({
            type: 'course',
            id: course.id,
            name: `${course.name} - ${classItem.title}`,
            variantId: null,
            variantName: null,
            classId: classItem.id,
            unitPrice: parseFloat(course.price || 0),
            sku: null,
            image: null,
        });
        setClassSelector(null);
    }, [addToCart]);

    const handleVariantSelected = useCallback((product, variant) => {
        addToCart({
            type: 'product',
            id: product.id,
            name: product.name,
            variantId: variant.id,
            variantName: variant.name,
            classId: null,
            unitPrice: parseFloat(variant.price || product.base_price),
            sku: variant.sku || null,
            image: product.primary_image?.url || null,
        });
        setVariantSelector(null);
    }, [addToCart]);

    const handleSaleComplete = useCallback((sale) => {
        setCompletedSale(sale);
        setShowPayment(false);
    }, []);

    const handleNewSale = useCallback(() => {
        setCompletedSale(null);
        clearCart();
    }, [clearCart]);

    const handleCharge = useCallback(() => {
        setShowCartDrawer(false);
        setShowPayment(true);
    }, []);

    if (completedSale) {
        return <SaleComplete sale={completedSale} onNewSale={handleNewSale} />;
    }

    const subtotal = cart.reduce((sum, item) => sum + item.totalPrice, 0);

    const cartProps = {
        cart,
        customer,
        onCustomerChange: setCustomer,
        onUpdateQuantity: updateQuantity,
        onRemoveItem: removeFromCart,
        onClearCart: clearCart,
        onCharge: handleCharge,
        subtotal,
        discount,
        onDiscountChange: setDiscount,
        postage,
        onPostageChange: setPostage,
    };

    return (
        <div className="h-full flex flex-col">
            {/* Header */}
            <header className="bg-white border-b border-gray-200 shrink-0">
                <div className="px-3 sm:px-4 py-2 sm:py-3 flex items-center justify-between">
                    <div className="flex items-center gap-2 sm:gap-3">
                        <img src="/images/bedaie-brand.png" alt="BeDaie" className="h-7 sm:h-8 w-auto object-contain" />
                        <h1 className="text-base sm:text-lg font-semibold text-gray-900">
                            {isSmallMobile ? 'POS' : 'Point of Sale'}
                        </h1>
                    </div>
                    <div className="flex items-center gap-2 sm:gap-3">
                        {!isMobile && (
                            <span className="text-sm text-gray-500">{config.user?.name}</span>
                        )}
                        <a
                            href={config.dashboardUrl || '/dashboard'}
                            className="px-2 sm:px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-1.5"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            {!isMobile && <span>Back to Admin</span>}
                        </a>
                    </div>
                </div>

                {/* Tab Navigation */}
                <nav className="px-3 sm:px-4 flex gap-1">
                    {TABS.map(tab => (
                        <button
                            key={tab.key}
                            onClick={() => setView(tab.key)}
                            className={`flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 text-sm font-medium border-b-2 transition-colors ${
                                view === tab.key
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            {tab.icon}
                            <span className={isSmallMobile ? 'hidden' : ''}>{tab.label}</span>
                        </button>
                    ))}
                </nav>
            </header>

            {/* Tab Content */}
            {view === VIEWS.POS && (
                <div className="flex-1 flex overflow-hidden relative">
                    {/* Product Grid - full width on mobile, flex-1 on desktop */}
                    <div className="flex-1 overflow-hidden">
                        <ProductSearch
                            onProductClick={handleProductClick}
                            onPackageClick={handlePackageClick}
                            onCourseClick={handleCourseClick}
                        />
                    </div>

                    {/* Desktop: Side-by-side Cart Panel */}
                    {!isMobile && (
                        <div className={`border-l border-gray-200 bg-white flex flex-col shrink-0 ${
                        isLargeDesktop ? 'w-[440px]' : isSmallDesktop ? 'w-80' : 'w-96'
                    }`}>
                            <CartPanel {...cartProps} />
                        </div>
                    )}

                    {/* Mobile: Cart Drawer Overlay */}
                    {isMobile && showCartDrawer && (
                        <>
                            <div
                                className="fixed inset-0 bg-black/50 z-40 cart-backdrop-enter"
                                onClick={() => setShowCartDrawer(false)}
                            />
                            <div className="fixed inset-y-0 right-0 w-full max-w-md bg-white z-50 shadow-xl flex flex-col cart-drawer-enter">
                                <div className="px-4 py-2.5 flex items-center justify-between border-b border-gray-200 shrink-0">
                                    <span className="text-sm font-semibold text-gray-900">Cart ({cartItemCount})</span>
                                    <button
                                        onClick={() => setShowCartDrawer(false)}
                                        className="p-2 -mr-2 text-gray-400 hover:text-gray-600 active:text-gray-800"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <CartPanel {...cartProps} />
                            </div>
                        </>
                    )}

                    {/* Mobile: Floating Cart Button */}
                    {isMobile && !showCartDrawer && (
                        <button
                            onClick={() => setShowCartDrawer(true)}
                            className="fixed bottom-6 right-4 z-30 bg-blue-600 text-white rounded-2xl shadow-lg hover:bg-blue-700 active:bg-blue-800 transition-colors px-4 py-3 flex items-center gap-3"
                        >
                            <div className="relative">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                                </svg>
                                {cartItemCount > 0 && (
                                    <span className="absolute -top-2 -right-2.5 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                        {cartItemCount > 9 ? '9+' : cartItemCount}
                                    </span>
                                )}
                            </div>
                            <div className="text-left">
                                <div className="text-xs opacity-80">{cartItemCount} item{cartItemCount !== 1 ? 's' : ''}</div>
                                <div className="text-sm font-bold">RM {subtotal.toFixed(2)}</div>
                            </div>
                        </button>
                    )}
                </div>
            )}

            {view === VIEWS.HISTORY && (
                <div className="flex-1 overflow-hidden">
                    <SalesHistory isMobile={isMobile} />
                </div>
            )}

            {view === VIEWS.REPORT && (
                <div className="flex-1 overflow-hidden">
                    <PosReport isMobile={isMobile} />
                </div>
            )}

            {/* Modals */}
            {showPayment && (
                <PaymentModal
                    cart={cart}
                    customer={customer}
                    subtotal={subtotal}
                    discount={discount}
                    postage={postage}
                    onClose={() => setShowPayment(false)}
                    onComplete={handleSaleComplete}
                />
            )}

            {classSelector && (
                <ClassSelector
                    course={classSelector}
                    onSelect={(classItem) => handleClassSelected(classSelector, classItem)}
                    onClose={() => setClassSelector(null)}
                />
            )}

            {variantSelector && (
                <VariantSelector
                    product={variantSelector}
                    onSelect={(variant) => handleVariantSelected(variantSelector, variant)}
                    onClose={() => setVariantSelector(null)}
                />
            )}
        </div>
    );
}
