import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function FlashMessage() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState<{ text: string; type: 'success' | 'error' } | null>(null);

    useEffect(() => {
        if (flash.success) {
            setMessage({ text: flash.success, type: 'success' });
            setVisible(true);
            const t = setTimeout(() => setVisible(false), 4000);
            return () => clearTimeout(t);
        }
        if (flash.error) {
            setMessage({ text: flash.error, type: 'error' });
            setVisible(true);
            const t = setTimeout(() => setVisible(false), 5000);
            return () => clearTimeout(t);
        }
    }, [flash.success, flash.error]);

    if (!visible || !message) {
        return null;
    }

    return (
        <div
            className={`fixed bottom-4 right-4 z-50 flex max-w-sm items-center gap-3 rounded-lg px-4 py-3 shadow-lg transition-all ${
                message.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'
            }`}
        >
            <span className="text-sm font-medium">{message.text}</span>
            <button onClick={() => setVisible(false)} className="ml-auto text-white/80 hover:text-white">
                ×
            </button>
        </div>
    );
}
