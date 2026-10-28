import { type LucideIcon } from 'lucide-react';

type WorkflowAnimationProps = {
  label: string;
  steps: { icon: LucideIcon; text: string }[];
};

export default function WorkflowAnimation({ label, steps }: WorkflowAnimationProps) {
  return <div role="img" aria-label={label} className="harbor-workflow relative flex w-full max-w-[430px] shrink-0 items-center justify-between gap-1 py-2 sm:gap-2">
    <style>{`
      @keyframes harbor-workflow-step {
        0%, 18% { transform: translateY(-2px) scale(1.04); background: rgba(34,197,94,.2); box-shadow: 0 0 0 2px rgba(15,148,136,.22); }
        28%, 100% { transform: translateY(0) scale(1); background: rgba(18,60,92,.045); box-shadow: none; }
      }
      @keyframes harbor-workflow-dot {
        from { left: 7%; opacity: 0; }
        8% { opacity: 1; }
        92% { opacity: 1; }
        to { left: 93%; opacity: 0; }
      }
      .harbor-workflow-node { animation: harbor-workflow-step 5.6s ease-in-out infinite; }
      .harbor-workflow-dot { animation: harbor-workflow-dot 5.6s linear infinite; }
      @media (prefers-reduced-motion: reduce) {
        .harbor-workflow-node, .harbor-workflow-dot { animation: none !important; }
      }
    `}</style>
    <div aria-hidden="true" className="pointer-events-none absolute bottom-[7px] left-[8%] right-[8%] h-px bg-[#0F9488]/30"><span className="harbor-workflow-dot absolute -top-[3px] h-[7px] w-[7px] rounded-full bg-[#0F9488]" /></div>
    {steps.map((step, index) => { const Icon = step.icon; return <div key={step.text} className="relative z-10 flex min-w-0 flex-1 flex-col items-center gap-1 rounded-xl px-1.5 py-2 text-center text-foreground harbor-workflow-node" style={{ animationDelay: `${index * 1.4}s` }}>
      <Icon aria-hidden="true" className="h-5 w-5 text-[#0F9488]" /><span className="whitespace-nowrap text-[10px] font-bold sm:text-xs">{step.text}</span>
    </div>; })}
  </div>;
}
