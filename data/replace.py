import os
import re

ROOT_DIR = r"C:\Users\larnz\Desktop\default website\data"
NEW_PREFIX = ""

# همین پسوندهای باینری رو نادیده بگیر
SKIP_EXTENSIONS = {'.png', '.jpg', ...}

def add_prefix(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except:
        return

    # الگوی پیدا کردن "image": "نام فایل"
    def replacer(match):
        return f'"image": "{NEW_PREFIX}{match.group(1)}"'

    new_content = re.sub(r'"image":\s*"([^"]+\.(?:png|jpg|jpeg|gif|webp|svg))"', replacer, content)
    if new_content != content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"به‌روز شد: {filepath}")

# اجرا
for foldername, subfolders, filenames in os.walk(ROOT_DIR):
    for fname in filenames:
        if os.path.splitext(fname)[1].lower() not in SKIP_EXTENSIONS:
            add_prefix(os.path.join(foldername, fname))