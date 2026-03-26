import { useState, useEffect, useRef } from 'react';
import { Search, X } from 'lucide-react';
import { Input } from './ui/input';
import { cn } from '../lib/utils';

export default function SearchInput({
    value = '',
    onChange,
    placeholder = 'Search...',
    debounceMs = 300,
    className,
}) {
    const [localValue, setLocalValue] = useState(value);
    const timerRef = useRef(null);

    useEffect(() => {
        setLocalValue(value);
    }, [value]);

    function handleChange(e) {
        const newValue = e.target.value;
        setLocalValue(newValue);

        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }

        timerRef.current = setTimeout(() => {
            onChange?.(newValue);
        }, debounceMs);
    }

    function handleClear() {
        setLocalValue('');
        onChange?.('');
    }

    useEffect(() => {
        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
        };
    }, []);

    return (
        <div className={cn('relative', className)}>
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
            <Input
                value={localValue}
                onChange={handleChange}
                placeholder={placeholder}
                className="pl-9 pr-9"
            />
            {localValue && (
                <button
                    type="button"
                    onClick={handleClear}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
