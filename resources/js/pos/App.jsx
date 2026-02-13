import React, { useState, useCallback } from 'react';
import ProductSearch from './components/ProductSearch';
import CartPanel from './components/CartPanel';
import PaymentModal from './components/PaymentModal';
import SaleComplete from './components/SaleComplete';
import SalesHistory from './components/SalesHistory';
import ClassSelector from './components/ClassSelector';
import VariantSelector from './components/VariantSelector';

const VIEWS = { POS: 'pos', HISTORY: 'history' };

export default function App() {
    const config = window.posConfig || {};
    const [view, setView] = useState(VIEWS.POS);
    const [cart, setCart] = useState([]);
    const [customer, setCustomer] = useState(null);
    const [showPayment, setShowPayment] = useState(false);
    const [completedSale, setCompletedSale] = useState(null);
    const [classSelector, setClassSelector] = useState(null);
    const [variantSelector, setVariantSelector] = useState(null);
    const [discount, setDiscount] = useState({ amount: 0, type: 'fixed' });

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

    if (completedSale) {
        return <SaleComplete sale={completedSale} onNewSale={handleNewSale} />;
    }

    if (view === VIEWS.HISTORY) {
        return <SalesHistory onBack={() => setView(VIEWS.POS)} />;
    }

    const subtotal = cart.reduce((sum, item) => sum + item.totalPrice, 0);

    return (
        <div className="h-full flex flex-col">
            {/* Header */}
            <header className="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between shrink-0">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h1 className="text-lg font-semibold text-gray-900">Point of Sale</h1>
                </div>
                <div className="flex items-center gap-3">
                    <span className="text-sm text-gray-500">{config.user?.name}</span>
                    <button
                        onClick={() => setView(VIEWS.HISTORY)}
                        className="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                    >
                        Sales History
                    </button>
                    <a
                        href={config.dashboardUrl || '/dashboard'}
                        className="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                    >
                        Back to Admin
                    </a>
                </div>
            </header>

            {/* Main Content */}
            <div className="flex-1 flex overflow-hidden">
                {/* Product Grid */}
                <div className="flex-1 overflow-hidden">
                    <ProductSearch
                        onProductClick={handleProductClick}
                        onPackageClick={handlePackageClick}
                        onCourseClick={handleCourseClick}
                    />
                </div>

                {/* Cart Panel */}
                <div className="w-96 border-l border-gray-200 bg-white flex flex-col">
                    <CartPanel
                        cart={cart}
                        customer={customer}
                        onCustomerChange={setCustomer}
                        onUpdateQuantity={updateQuantity}
                        onRemoveItem={removeFromCart}
                        onClearCart={clearCart}
                        onCharge={() => setShowPayment(true)}
                        subtotal={subtotal}
                        discount={discount}
                        onDiscountChange={setDiscount}
                    />
                </div>
            </div>

            {/* Modals */}
            {showPayment && (
                <PaymentModal
                    cart={cart}
                    customer={customer}
                    subtotal={subtotal}
                    discount={discount}
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
