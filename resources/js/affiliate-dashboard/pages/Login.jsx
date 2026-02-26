import React, { useState } from 'react';
import api from '../services/api';
import CountryCodeSelect from '../components/CountryCodeSelect';
import { DEFAULT_COUNTRY_CODE } from '../data/countryCodes';

export default function Login({ onLogin, navigate }) {
    const [phone, setPhone] = useState('');
    const [countryCode, setCountryCode] = useState(DEFAULT_COUNTRY_CODE);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const formatPhoneForApi = (input) => {
        let digits = input.replace(/\D/g, '');
        if (digits.startsWith('0')) {
            digits = digits.slice(1);
        }
        return countryCode + digits;
    };

    const handlePhoneChange = (e) => {
        const value = e.target.value.replace(/[^\d]/g, '');
        setPhone(value);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);

        try {
            const formattedPhone = formatPhoneForApi(phone);
            const data = await api.login(formattedPhone);
            onLogin(data.affiliate || data.user || data);
        } catch (err) {
            setError(err.message || 'Nombor telefon tidak dijumpai. Sila semak semula.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <h2 className="text-xl font-semibold text-gray-900 text-center">Log Masuk</h2>
            <p className="mt-1 text-sm text-gray-500 text-center">
                Masukkan nombor telefon anda untuk meneruskan
            </p>

            {error && (
                <div className="mt-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-3">
                    {error}
                </div>
            )}

            <form onSubmit={handleSubmit} className="mt-6 space-y-5">
                <div>
                    <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1.5">
                        Nombor Telefon
                    </label>
                    <div className="flex">
                        <CountryCodeSelect
                            value={countryCode}
                            onChange={setCountryCode}
                        />
                        <input
                            id="phone"
                            type="tel"
                            inputMode="numeric"
                            value={phone}
                            onChange={handlePhoneChange}
                            placeholder="1234567890"
                            required
                            autoComplete="tel"
                            className="block w-full rounded-r-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                        />
                    </div>
                    <p className="mt-1.5 text-xs text-gray-400">Contoh: 1234567890</p>
                </div>

                <button
                    type="submit"
                    disabled={loading || !phone}
                    className="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    {loading ? (
                        <span className="inline-flex items-center gap-2">
                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            Sedang log masuk...
                        </span>
                    ) : 'Log Masuk'}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-gray-500">
                Belum mempunyai akaun?{' '}
                <a
                    href="/affiliate/register"
                    onClick={(e) => {
                        e.preventDefault();
                        navigate('/affiliate/register');
                    }}
                    className="font-semibold text-indigo-600 hover:text-indigo-500"
                >
                    Daftar di sini
                </a>
            </p>
        </div>
    );
}
