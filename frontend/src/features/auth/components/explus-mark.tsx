/**
 * Dấu hiệu nhận diện Explus.
 *
 * Dấu cộng trong ô vuông — "plus" trong tên công ty, vẽ theo tinh thần bản vẽ
 * kỹ thuật: nét mảnh đều, góc vuông, không bo tròn.
 */
export function ExplusMark({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 32 32"
      fill="none"
      aria-hidden="true"
      className={className}
    >
      <rect
        x="0.5"
        y="0.5"
        width="31"
        height="31"
        stroke="currentColor"
        strokeWidth="1"
      />
      <path
        d="M16 8v16M8 16h16"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinecap="square"
      />
    </svg>
  );
}

/** Chữ ký thương hiệu: dấu + tên. */
export function ExplusWordmark({ className }: { className?: string }) {
  return (
    <div className={`flex items-center gap-2.5 ${className ?? ""}`}>
      <ExplusMark className="text-accent h-6 w-6" />
      <span className="text-[1.35rem] leading-none font-semibold tracking-tight">
        explus
      </span>
    </div>
  );
}
