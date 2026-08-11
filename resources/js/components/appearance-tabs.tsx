import type { LucideIcon } from "lucide-react";
import { Monitor, Moon, Sun } from "lucide-react";
import type { FieldsetHTMLAttributes } from "react";

import type { Appearance } from "@/hooks/use-appearance";
import { useAppearance } from "@/hooks/use-appearance";
import { cn } from "@/lib/utils";

export default function AppearanceToggleTab({
  className = "",
  ...props
}: FieldsetHTMLAttributes<HTMLFieldSetElement>) {
  const { appearance, updateAppearance } = useAppearance();

  const tabs: {
    value: Appearance;
    icon: LucideIcon;
    label: string;
  }[] = [
    { value: "light", icon: Sun, label: "Light" },
    { value: "dark", icon: Moon, label: "Dark" },
    { value: "system", icon: Monitor, label: "System" },
  ];

  return (
    <fieldset
      className={cn(
        "m-0 inline-flex gap-1 rounded-lg border-0 bg-neutral-100 p-1 dark:bg-neutral-800",
        className,
      )}
      {...props}
    >
      <legend className="sr-only">表示モード</legend>
      {tabs.map(({ value, icon: Icon, label }) => (
        <label
          key={value}
          className={cn(
            "flex cursor-pointer items-center rounded-md transition-colors",
            appearance === value
              ? "bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100"
              : "text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60",
          )}
        >
          <input
            type="radio"
            name="appearance"
            value={value}
            checked={appearance === value}
            onChange={() => updateAppearance(value)}
            className="peer sr-only"
          />
          <span className="flex items-center rounded-md px-3.5 py-1.5 transition-[box-shadow] peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-neutral-100 dark:peer-focus-visible:ring-offset-neutral-800">
            <Icon className="-ml-1 h-4 w-4" />
            <span className="ml-1.5 text-sm">{label}</span>
          </span>
        </label>
      ))}
    </fieldset>
  );
}
