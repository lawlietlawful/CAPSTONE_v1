import os
import glob

modules = ['seminars', 'behavioral-reports']

for module in modules:
    admin_dir = rf'd:\CAPSTONE PROJECT\student-referral-system\resources\views\admin\{module}'
    counselor_dir = rf'd:\CAPSTONE PROJECT\student-referral-system\resources\views\counselor\{module}'

    # Create counselor dir if it doesn't exist
    os.makedirs(counselor_dir, exist_ok=True)

    for file_path in glob.glob(os.path.join(admin_dir, '*.blade.php')):
        filename = os.path.basename(file_path)
        dst_path = os.path.join(counselor_dir, filename)
        
        with open(file_path, 'r', encoding='utf-8') as f:
            text = f.read()
            
        text = text.replace("route('admin.", "route('counselor.")
        text = text.replace("@extends('layouts.admin')", "@extends('layouts.counselor')")
        text = text.replace("x-data=\"calendarView()\"", "x-data=\"counselorCalendarView()\"") # just in case
        
        with open(dst_path, 'w', encoding='utf-8') as f:
            f.write(text)

print("All views synced!")
