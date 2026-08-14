import sys
import os

controllers = ['SeminarController', 'BehavioralReportController']

for controller in controllers:
    src = rf'd:\CAPSTONE PROJECT\student-referral-system\app\Http\Controllers\Admin\{controller}.php'
    dst = rf'd:\CAPSTONE PROJECT\student-referral-system\app\Http\Controllers\Counselor\{controller}.php'

    if not os.path.exists(src):
        print(f"Source not found: {src}")
        continue

    with open(src, 'r', encoding='utf-8') as f:
        text = f.read()

    text = text.replace(r'namespace App\Http\Controllers\Admin;', r'namespace App\Http\Controllers\Counselor;')
    text = text.replace("route('admin.", "route('counselor.")
    text = text.replace("view('admin.", "view('counselor.")

    with open(dst, 'w', encoding='utf-8') as f:
        f.write(text)

print("All controllers synced!")
