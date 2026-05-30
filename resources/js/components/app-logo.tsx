import { Zap } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div
                style={{
                    width: 36,
                    height: 36,
                    borderRadius: 10,
                    background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #a855f7 100%)',
                    boxShadow: '0 6px 18px rgba(124,92,246,.28)',
                    display: 'grid',
                    placeItems: 'center',
                    flexShrink: 0,
                    position: 'relative',
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        position: 'absolute',
                        inset: 0,
                        background: 'linear-gradient(120deg, rgba(255,255,255,.45), transparent 45%)',
                    }}
                />
                <Zap size={18} className="text-white relative z-10" fill="white" />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span
                    style={{
                        fontFamily: "'Sora', system-ui, sans-serif",
                        fontWeight: 700,
                        fontSize: 15,
                        letterSpacing: '-0.02em',
                        lineHeight: 1.1,
                        color: '#1a1b2e',
                    }}
                >
                    Befit
                </span>
                <span
                    style={{
                        fontSize: 10,
                        fontWeight: 700,
                        letterSpacing: '0.08em',
                        textTransform: 'uppercase',
                        color: '#8487a8',
                        lineHeight: 1,
                    }}
                >
                    Automation
                </span>
            </div>
        </>
    );
}
