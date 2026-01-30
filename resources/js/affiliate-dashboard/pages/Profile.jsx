import React, { useState } from 'react';
import api from '../services/api';

export default function Profile({ user, setUser, onLogout }) {
    const [name, setName] = useState(user?.name || '');
    const [phone, setPhone] = useState(user?.phone || '');
    const [email, setEmail] = useState(user?.email || '');
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState('');
    const [loggingOut, setLoggingOut] = useState(false);

    const handleSave = async (e) => {
        e.preventDefault();
        setError('');
        setSaved(false);
        setSaving(true);

        try {
            const config = window.affiliateConfig || {};
            const url = `${config.apiBaseUrl || '/affiliate-api'}/profile`;
            const response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ name, phone, email: email || undefined }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw { message: data.message || 'Gagal mengemas kini profil.' };
            }

            setUser(data.user || data);
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        } catch (err) {
            setError(err.message || 'Gagal mengemas kini profil.');
        } finally {
            setSaving(false);
        }
    };

    const handleLogout = async () => {
        setLoggingOut(true);
        await onLogout();
    };

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-semibold text-gray-900">Profil</h2>
                <p className="text-sm text-gray-500">Urus butiran akaun anda</p>
            </div>

            {user?.ref_code && (
                <div className="bg-indigo-50 rounded-xl border border-indigo-100 p-4">
                    <p className="text-xs font-medium text-indigo-600 uppercase tracking-wide">Kod Rujukan Anda</p>
                    <p className="text-lg font-semibold text-indigo-900 mt-1">{user.ref_code}</p>
                </div>
            )}

            <div className="bg-white rounded-xl border border-gray-200 p-4">
                {error && (
                    <div className="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3">
                        {error}
                    </div>
                )}

                {saved && (
                    <div className="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg p-3">
                        Profil berjaya dikemas kini.
                    </div>
                )}

                <form onSubmit={handleSave} className="space-y-4">
                    <div>
                        <label htmlFor="profile-name" className="block text-sm font-medium text-gray-700">
                            Nama
                        </label>
                        <input
                            id="profile-name"
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label htmlFor="profile-phone" className="block text-sm font-medium text-gray-700">
                            Nombor Telefon
                        </label>
                        <input
                            id="profile-phone"
                            type="tel"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            required
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label htmlFor="profile-email" className="block text-sm font-medium text-gray-700">
                            Emel <span className="text-gray-400">(pilihan)</span>
                        </label>
                        <input
                            id="profile-email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={saving}
                        className="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </button>
                </form>
            </div>

            <button
                onClick={handleLogout}
                disabled={loggingOut}
                className="w-full rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
                {loggingOut ? 'Sedang keluar...' : 'Log Keluar'}
            </button>
        </div>
    );
}
