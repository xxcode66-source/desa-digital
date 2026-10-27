import os
import zipfile

# Direktori/ekstensi turunan yang tidak boleh ikut ke arsip sumber.
EXCLUDE_DIRS = {
    ".git", ".gradle", ".idea", "build", "app/build", "node_modules",
    "vendor", "__pycache__", ".dart_tool", "dist", ".pytest_cache",
}
EXCLUDE_DIR_NAMES = {
    ".git", ".gradle", ".idea", "build", "node_modules",
    "vendor", "__pycache__", "dist", ".pytest_cache", ".dart_tool",
}
EXCLUDE_EXTENSIONS = {".apk", ".aab", ".zip", ".pyc", ".log"}
# File yang tetap harus ikut meski ekstensinya dikecualikan (mis. wrapper jar).
ALWAYS_KEEP_BASENAMES = {"gradle-wrapper.jar"}
EXCLUDE_FILES = {".env"}  # jangan pernah arsipkan kredensial


def _should_skip_dir(dirname):
    return dirname in EXCLUDE_DIR_NAMES


def zip_project(output_filename="aplikasi-desa-digital.zip"):
    print("Membuat file ZIP proyek (menyertakan sumber, mengecualikan artefak build)...")
    root = "."
    added = 0
    with zipfile.ZipFile(output_filename, "w", zipfile.ZIP_DEFLATED) as zipf:
        for dirpath, dirnames, filenames in os.walk(root):
            # Prune direktori turunan secara in-place agar os.walk tidak masuk.
            dirnames[:] = [d for d in dirnames if not _should_skip_dir(d)]

            # Lewati subtree android-app/app/build meski 'build' muncul di tengah path.
            rel = os.path.relpath(dirpath, root).replace("\\", "/")
            if "/build/" in f"/{rel}/" and rel.split("/")[0] in {"android-app"}:
                continue

            for filename in filenames:
                if filename in EXCLUDE_FILES:
                    continue
                if filename not in ALWAYS_KEEP_BASENAMES:
                    ext = os.path.splitext(filename)[1].lower()
                    if ext in EXCLUDE_EXTENSIONS:
                        continue
                full = os.path.join(dirpath, filename)
                arc = os.path.relpath(full, root)
                zipf.write(full, arc)
                added += 1

    size_mb = os.path.getsize(output_filename) / (1024 * 1024)
    print(f"Selesai! {added} file -> {output_filename} ({size_mb:.1f} MB)")


if __name__ == "__main__":
    zip_project()
