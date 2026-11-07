/**
 * Best-effort: wrap Arabic string literals in t("...", undefined, "...") for listed TSX files.
 * Usage: node lang/wrap_arabic_strings.mjs path1.tsx path2.tsx ...
 */
import fs from "node:fs";
import path from "node:path";

const ARABIC = /[\u0600-\u06FF]/;

function slugKey(s) {
    const base = s
        .replace(/\s+/g, "_")
        .replace(/[^\u0600-\u06FFa-zA-Z0-9_]/g, "")
        .slice(0, 40);
    let hash = 0;
    for (let i = 0; i < s.length; i++) hash = (hash * 31 + s.charCodeAt(i)) >>> 0;
    return `auto.${base || "s"}.${hash.toString(36)}`;
}

function ensureImport(src, rel) {
    if (/useT/.test(src) && /from ['"].*useT['"]/.test(src)) return src;
    const importLine = `import { useT } from "${rel}";\n`;
    if (/^import .+$/m.test(src)) {
        return src.replace(/^(import .+;\n)+/m, (m) => m + importLine);
    }
    return importLine + src;
}

function ensureHook(src) {
    if (/const t = useT\(/.test(src)) return src;
    // Insert after first function component signature opening brace of default export
    return src.replace(
        /(export default function \w+\s*\([^)]*\)\s*(?::\s*[^{]+)?\{)/,
        `$1\n    const t = useT();`,
    );
}

function wrapFile(filePath) {
    let src = fs.readFileSync(filePath, "utf8");
    if (!ARABIC.test(src) || /useT\(/.test(src) && (src.match(ARABIC) || []).length < 3) {
        // still process if has arabic
    }

    const depth = filePath.split(/resources[\\/]js[\\/]/)[1]?.split(/[\\/]/).length - 1 || 2;
    const rel = `${"../".repeat(depth)}Hooks/useT`;

    const keys = new Map(); // key -> arabic

    // Replace double-quoted Arabic strings used as JSX text/attrs (skip import paths)
    src = src.replace(/"([^"\n]*[\u0600-\u06FF][^"\n]*)"/g, (full, text, offset) => {
        const before = src.slice(Math.max(0, offset - 40), offset);
        if (/import\s+|from\s+$/.test(before)) return full;
        if (before.includes("undefined, ")) return full; // already wrapped fallback
        const key = slugKey(text);
        keys.set(key, text);
        return `t("${key}", undefined, "${text.replace(/\\/g, "\\\\").replace(/"/g, '\\"')}")`;
    });

    // Replace single-quoted Arabic strings similarly
    src = src.replace(/'([^'\n]*[\u0600-\u06FF][^'\n]*)'/g, (full, text, offset) => {
        const before = src.slice(Math.max(0, offset - 40), offset);
        if (/import\s+|from\s+$/.test(before)) return full;
        if (before.includes("undefined, ")) return full;
        const key = slugKey(text);
        keys.set(key, text);
        return `t("${key}", undefined, '${text.replace(/\\/g, "\\\\").replace(/'/g, "\\'")}')`;
    });

    if (keys.size === 0) return { filePath, keys: 0 };

    src = ensureImport(src, rel);
    src = ensureHook(src);

    fs.writeFileSync(filePath, src);
    return { filePath, keys };
}

const files = process.argv.slice(2);
const allKeys = new Map();
for (const f of files) {
    const abs = path.resolve(f);
    if (!fs.existsSync(abs)) {
        console.error("missing", abs);
        continue;
    }
    const result = wrapFile(abs);
    console.log(result.filePath, "keys", result.keys instanceof Map ? result.keys.size : result.keys);
    if (result.keys instanceof Map) {
        for (const [k, v] of result.keys) allKeys.set(k, v);
    }
}

const arPath = path.resolve("lang/ar.json");
const enPath = path.resolve("lang/en.json");
const ar = JSON.parse(fs.readFileSync(arPath, "utf8"));
const en = JSON.parse(fs.readFileSync(enPath, "utf8"));
for (const [k, v] of allKeys) {
    ar[k] = v;
    if (!en[k]) en[k] = v; // temporary EN=AR until edited; fallback still works in UI via third arg
}
fs.writeFileSync(arPath, JSON.stringify(ar, null, 4) + "\n");
fs.writeFileSync(enPath, JSON.stringify(en, null, 4) + "\n");
console.log("merged", allKeys.size, "keys into lang json");
