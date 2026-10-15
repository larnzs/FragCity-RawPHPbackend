import os
import sys

# ============================================
# تنظیمات - می‌تونی اینجا مقادیر رو عوض کنی
# ============================================
ROOT_DIR = r"C:\Users\larnz\Desktop\default website\weaponpaints\data"   # مسیر پوشه‌ی اصلی پنل (داخل کوتیشن بذار)
OLD_STR = ""
NEW_STR = ""                           # خالی یعنی حذف کن

# پسوند فایل‌هایی که نباید تغییر کنن (باینری)
SKIP_EXTENSIONS = {
    '.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico',
    '.ttf', '.woff', '.woff2', '.eot', '.mp3', '.mp4',
    '.zip', '.exe', '.dll', '.bin', '.dat'
}

def replace_in_file(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        print(f"خطا در خواندن {filepath}: {e}")
        return

    if OLD_STR in content:
        new_content = content.replace(OLD_STR, NEW_STR)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"به‌روز شد: {filepath}")

def main():
    if not os.path.isdir(ROOT_DIR):
        print("مسیر وارد شده وجود ندارد.")
        return

    for foldername, subfolders, filenames in os.walk(ROOT_DIR):
        for filename in filenames:
            ext = os.path.splitext(filename)[1].lower()
            if ext in SKIP_EXTENSIONS:
                continue
            filepath = os.path.join(foldername, filename)
            replace_in_file(filepath)

    print("عملیات با موفقیت به پایان رسید.")

if __name__ == "__main__":
    main()