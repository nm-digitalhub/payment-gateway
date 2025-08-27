<?php

namespace NMDigitalHub\PaymentGateway\Validators;

use Illuminate\Support\Facades\Validator;

class HebrewValidator
{
    /**
     * רשימת ולידטורים בעברית
     */
    public static function rules(): array
    {
        return [
            'hebrew_name' => 'regex:/^[\u05D0-\u05EA\s\-\'\"\.]+$/u',
            'israeli_id' => 'israeli_id',
            'israeli_phone' => 'regex:/^0[2-9][0-9]{1}[\-\s]?[0-9]{7}$|^[\+]?972[\-\s]?[2-9][0-9]{1}[\-\s]?[0-9]{7}$/',
            'hebrew_address' => 'regex:/^[\u05D0-\u05EA0-9\s\-\'\"\.\,\/]+$/u',
            'israeli_postal_code' => 'regex:/^[0-9]{5,7}$/',
            'cardcom_amount' => 'numeric|min:0.01|max:999999.99',
        ];
    }

    /**
     * הודעות שגיאה בעברית
     */
    public static function messages(): array
    {
        return [
            'hebrew_name.regex' => 'השם חייב להכיל אותיות עבריות בלבד',
            'israeli_id.israeli_id' => 'מספר זהות לא תקין',
            'israeli_phone.regex' => 'מספר טלפון לא תקין. נדרש פורמט ישראלי',
            'hebrew_address.regex' => 'הכתובת חייבת להכיל אותיות עבריות ומספרים בלבד',
            'israeli_postal_code.regex' => 'מיקוד חייב להכיל 5-7 ספרות',
            'cardcom_amount.numeric' => 'הסכום חייב להיות מספר',
            'cardcom_amount.min' => 'הסכום המינימלי לתשלום הוא 0.01 ₪',
            'cardcom_amount.max' => 'הסכום המקסימלי לתשלום הוא 999,999.99 ₪',
            'required' => 'שדה זה הוא חובה',
            'email' => 'כתובת האימייל לא תקינה',
            'max' => 'השדה ארוך מדי (מקסימום :max תווים)',
            'min' => 'השדה קצר מדי (מינימום :min תווים)',
            'string' => 'השדה חייב להיות טקסט',
            'integer' => 'השדה חייב להיות מספר שלם',
            'boolean' => 'השדה חייב להיות כן או לא',
            'exists' => 'הערך שנבחר אינו תקין',
            'confirmed' => 'אימות השדה לא תואם',
            'unique' => 'הערך כבר קיים במערכת',
            'date' => 'התאריך לא תקין',
            'after' => 'התאריך חייב להיות אחרי :date',
            'before' => 'התאריך חייב להיות לפני :date',
            'in' => 'הערך שנבחר אינו תקין',
            'not_in' => 'הערך שנבחר אינו מותר',
            'regex' => 'פורמט השדה אינו תקין'
        ];
    }

    /**
     * ולידציה ישראלית לזהות
     */
    public static function validateIsraeliId(string $id): bool
    {
        // הסרת רווחים ומקפים
        $id = preg_replace('/[\s\-]/', '', $id);
        
        // בדיקה שמדובר ב-9 ספרות
        if (!preg_match('/^\d{9}$/', $id)) {
            return false;
        }

        // אלגוריתם בדיקת ת"ז ישראלית
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $id[$i];
            $weight = ($i % 2) + 1;
            $product = $digit * $weight;
            
            // אם התוצר גדול מ-9, חיסור 9
            if ($product > 9) {
                $product -= 9;
            }
            
            $sum += $product;
        }

        return $sum % 10 === 0;
    }

    /**
     * ולידציה לטלפון ישראלי
     */
    public static function validateIsraeliPhone(string $phone): bool
    {
        // הסרת רווחים ומקפים
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        // פורמטים מותרים:
        // 050-1234567, 0501234567
        // +972-50-1234567, +972501234567
        // 972-50-1234567, 972501234567
        
        $patterns = [
            '/^0[2-9][0-9]{8}$/',              // 0501234567
            '/^\+972[2-9][0-9]{8}$/',          // +972501234567
            '/^972[2-9][0-9]{8}$/',            // 972501234567
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ולידציה לכתובת עברית
     */
    public static function validateHebrewAddress(string $address): bool
    {
        // בדיקה שהכתובת מכילה לפחות אות אחת בעברית
        if (!preg_match('/[\u05D0-\u05EA]/u', $address)) {
            return false;
        }
        
        // בדיקה שאין תווים לא מותרים
        return preg_match('/^[\u05D0-\u05EA0-9\s\-\'\"\.\,\/]+$/u', $address);
    }

    /**
     * ולידציה לשם בעברית
     */
    public static function validateHebrewName(string $name): bool
    {
        // בדיקה שהשם מכיל לפחות אות אחת בעברית
        if (!preg_match('/[\u05D0-\u05EA]/u', $name)) {
            return false;
        }
        
        // בדיקה שאין תווים לא מותרים (רק אותיות עבריות, רווחים, מקפים, גרשיים)
        return preg_match('/^[\u05D0-\u05EA\s\-\'\"\.]+$/u', $name);
    }

    /**
     * רגיסטרציה של הולידטורים במערכת Laravel
     */
    public static function register(): void
    {
        // ולידטור ת"ז ישראלית
        Validator::extend('israeli_id', function ($attribute, $value, $parameters, $validator) {
            return static::validateIsraeliId($value);
        });

        // ולידטור טלפון ישראלי
        Validator::extend('israeli_phone', function ($attribute, $value, $parameters, $validator) {
            return static::validateIsraeliPhone($value);
        });

        // ולידטור כתובת עברית
        Validator::extend('hebrew_address', function ($attribute, $value, $parameters, $validator) {
            return static::validateHebrewAddress($value);
        });

        // ולידטור שם עברי
        Validator::extend('hebrew_name', function ($attribute, $value, $parameters, $validator) {
            return static::validateHebrewName($value);
        });

        // הודעות שגיאה בעברית
        Validator::replacer('israeli_id', function ($message, $attribute, $rule, $parameters) {
            return 'מספר הזהות אינו תקין';
        });

        Validator::replacer('israeli_phone', function ($message, $attribute, $rule, $parameters) {
            return 'מספר הטלפון אינו תקין (נדרש פורמט ישראלי)';
        });

        Validator::replacer('hebrew_address', function ($message, $attribute, $rule, $parameters) {
            return 'הכתובת חייבת להכיל אותיות עבריות';
        });

        Validator::replacer('hebrew_name', function ($message, $attribute, $rule, $parameters) {
            return 'השם חייב להכיל אותיות עבריות';
        });
    }

    /**
     * חבילת כללי ולידציה לטפסי תשלום
     */
    public static function paymentFormRules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255', 'hebrew_name'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'israeli_phone'],
            'israeli_id' => ['nullable', 'string', 'israeli_id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:500'],
            'billing_address' => ['nullable', 'string', 'max:255', 'hebrew_address'],
            'billing_city' => ['nullable', 'string', 'max:100', 'hebrew_name'],
            'postal_code' => ['nullable', 'string', 'regex:/^[0-9]{5,7}$/'],
        ];
    }

    /**
     * הודעות שגיאה מותאמות לטפסי תשלום
     */
    public static function paymentFormMessages(): array
    {
        return array_merge(static::messages(), [
            'customer_name.required' => 'נדרש להזין שם מלא',
            'customer_name.hebrew_name' => 'השם חייב להכיל אותיות עבריות',
            'customer_email.required' => 'נדרש להזין כתובת אימייל',
            'customer_email.email' => 'כתובת האימייל אינה תקינה',
            'customer_phone.israeli_phone' => 'מספר הטלפון אינו תקין (לדוגמה: 050-1234567)',
            'israeli_id.israeli_id' => 'מספר הזהות אינו תקין',
            'amount.required' => 'נדרש להזין סכום לתשלום',
            'amount.min' => 'הסכום המינימלי לתשלום הוא 0.01 ₪',
            'amount.max' => 'הסכום המקסימלי לתשלום הוא 999,999.99 ₪',
            'billing_address.hebrew_address' => 'הכתובת חייבת להכיל אותיות עבריות',
            'billing_city.hebrew_name' => 'שם העיר חייב להכיל אותיות עבריות',
            'postal_code.regex' => 'מיקוד חייב להכיל 5-7 ספרות'
        ]);
    }

    /**
     * פורמטים נפוצים לבדיקת תקינות נתונים
     */
    public static function getCommonPatterns(): array
    {
        return [
            'hebrew_letters' => '/^[\u05D0-\u05EA\s]+$/u',
            'hebrew_with_punctuation' => '/^[\u05D0-\u05EA\s\-\'\"\.\,\!\?]+$/u',
            'israeli_id' => '/^\d{9}$/',
            'israeli_phone_basic' => '/^0[2-9][0-9]{8}$/',
            'israeli_phone_international' => '/^(\+972|972)[2-9][0-9]{8}$/',
            'israeli_postal_code' => '/^[0-9]{5,7}$/',
            'hebrew_address' => '/^[\u05D0-\u05EA0-9\s\-\'\"\.\,\/]+$/u',
            'amount_ils' => '/^\d{1,6}(\.\d{1,2})?$/',
            'credit_card_cvv' => '/^\d{3,4}$/',
            'credit_card_expiry' => '/^(0[1-9]|1[0-2])\/\d{2}$/' // MM/YY
        ];
    }
}