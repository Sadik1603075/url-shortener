import { Link2 } from 'lucide-react';

export default function Logo() {
  return (
    <div className="logo">
      <div className="logo-mark">
        <Link2 size={20} strokeWidth={2.5} />
      </div>

      <span>LinkForge</span>
    </div>
  );
}