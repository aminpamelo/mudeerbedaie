import { useState } from 'react';
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from './ui/table';
import { cn } from '../lib/utils';

/**
 * @param {Object} props
 * @param {{ key: string, label: string, sortable?: boolean, className?: string, render?: (row: any) => React.ReactNode }[]} props.columns
 * @param {any[]} props.data
 * @param {string} [props.emptyMessage]
 * @param {string} [props.sortKey]
 * @param {'asc' | 'desc'} [props.sortDirection]
 * @param {(key: string) => void} [props.onSort]
 */
export default function DataTable({
    columns,
    data,
    emptyMessage = 'No records found.',
    sortKey,
    sortDirection,
    onSort,
}) {
    const [localSortKey, setLocalSortKey] = useState(null);
    const [localSortDir, setLocalSortDir] = useState('asc');

    const activeSortKey = sortKey ?? localSortKey;
    const activeSortDir = sortDirection ?? localSortDir;

    function handleSort(key) {
        if (onSort) {
            onSort(key);
            return;
        }
        if (localSortKey === key) {
            setLocalSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setLocalSortKey(key);
            setLocalSortDir('asc');
        }
    }

    const sortedData =
        !onSort && localSortKey
            ? [...data].sort((a, b) => {
                  const aVal = a[localSortKey] ?? '';
                  const bVal = b[localSortKey] ?? '';
                  const cmp =
                      typeof aVal === 'string'
                          ? aVal.localeCompare(bVal)
                          : aVal - bVal;
                  return localSortDir === 'asc' ? cmp : -cmp;
              })
            : data;

    function SortIcon({ columnKey }) {
        if (activeSortKey !== columnKey) {
            return <ArrowUpDown className="ml-1 h-3.5 w-3.5 text-zinc-400" />;
        }
        return activeSortDir === 'asc' ? (
            <ArrowUp className="ml-1 h-3.5 w-3.5 text-zinc-700" />
        ) : (
            <ArrowDown className="ml-1 h-3.5 w-3.5 text-zinc-700" />
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    {columns.map((col) => (
                        <TableHead
                            key={col.key}
                            className={cn(
                                col.sortable && 'cursor-pointer select-none',
                                col.className
                            )}
                            onClick={
                                col.sortable
                                    ? () => handleSort(col.key)
                                    : undefined
                            }
                        >
                            <span className="inline-flex items-center">
                                {col.label}
                                {col.sortable && (
                                    <SortIcon columnKey={col.key} />
                                )}
                            </span>
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {sortedData.length === 0 ? (
                    <TableRow>
                        <TableCell
                            colSpan={columns.length}
                            className="h-24 text-center text-zinc-500"
                        >
                            {emptyMessage}
                        </TableCell>
                    </TableRow>
                ) : (
                    sortedData.map((row, idx) => (
                        <TableRow key={row.id ?? idx}>
                            {columns.map((col) => (
                                <TableCell
                                    key={col.key}
                                    className={col.className}
                                >
                                    {col.render
                                        ? col.render(row)
                                        : row[col.key]}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))
                )}
            </TableBody>
        </Table>
    );
}
