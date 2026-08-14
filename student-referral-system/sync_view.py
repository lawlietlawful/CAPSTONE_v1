import sys

src = r'd:\CAPSTONE PROJECT\student-referral-system\resources\views\admin\seminars\show.blade.php'
dst = r'd:\CAPSTONE PROJECT\student-referral-system\resources\views\counselor\seminars\show.blade.php'

with open(src, 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace("route('admin.", "route('counselor.")
text = text.replace("@extends('layouts.admin')", "@extends('layouts.counselor')")

with open(dst, 'w', encoding='utf-8') as f:
    f.write(text)
