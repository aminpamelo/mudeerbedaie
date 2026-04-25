import { Download } from 'lucide-react';
import { Button } from '@/livehost/components/ui/button';

export default function ExportCsvButton({ exportPath, filters }) {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (Array.isArray(value)) value.forEach((v) => params.append(`${key}[]`, v));
    else if (value != null) params.append(key, value);
  });
  const href = `${exportPath}?${params.toString()}`;

  return (
    <Button asChild variant="outline" size="sm">
      <a href={href} download>
        <Download className="mr-1.5 size-4" /> Export CSV
      </a>
    </Button>
  );
}
