import * as React from "react";
import { cn } from "../ui/utils";

function DashboardCard({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      className={cn(
        "rounded-xl border border-slate-200/70 bg-white/95 shadow-md transition-all duration-300 ease-in-out hover:-translate-y-0.5 hover:shadow-xl",
        className,
      )}
      {...props}
    />
  );
}

export { DashboardCard };