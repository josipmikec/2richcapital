function richFormatDateTime(dateString, isDateOnly = false) {
    if (!dateString) return '-';
    
    // Default to system timezone if not set
    const tz = (window.USER_PREFS && window.USER_PREFS.timezone) ? window.USER_PREFS.timezone : Intl.DateTimeFormat().resolvedOptions().timeZone;
    const df = (window.USER_PREFS && window.USER_PREFS.date_format) ? window.USER_PREFS.date_format : 'Y-m-d';
    const tf = (window.USER_PREFS && window.USER_PREFS.time_format) ? window.USER_PREFS.time_format : 'H:i';

    let dStr = String(dateString).trim();
    if (isDateOnly) {
        dStr = dStr.split(' ')[0].split('T')[0]; // Extract just the date
        const parts = dStr.split('-');
        if (parts.length === 3) {
            // Reconstruct as local Date at noon to avoid timezone shifts
            const d = new Date(parts[0], parts[1] - 1, parts[2], 12, 0, 0);
            return formatJSDate(d, df, null, tz, true); // True means ignore timezone for date formatting
        }
        return dStr;
    }

    if (!dStr.includes('T') && !dStr.includes('Z')) {
        dStr = dStr.replace(' ', 'T') + 'Z';
    } else if (!dStr.includes('Z') && !dStr.includes('+') && !dStr.includes('-')) {
        dStr += 'Z';
    }

    const d = new Date(dStr);
    if (isNaN(d.getTime())) return dateString;

    return formatJSDate(d, df, tf, tz, false);
}

function formatJSDate(d, df, tf, tz, isDateOnly) {
    const optsDate = { timeZone: tz };
    
    // Map PHP date format to Intl
    if (df === 'd/m/Y') {
        optsDate.day = '2-digit'; optsDate.month = '2-digit'; optsDate.year = 'numeric';
    } else if (df === 'm/d/Y') {
        optsDate.day = '2-digit'; optsDate.month = '2-digit'; optsDate.year = 'numeric';
    } else if (df === 'F j, Y') {
        optsDate.month = 'long'; optsDate.day = 'numeric'; optsDate.year = 'numeric';
    } else { // Y-m-d
        optsDate.year = 'numeric'; optsDate.month = '2-digit'; optsDate.day = '2-digit';
    }

    let datePart = new Intl.DateTimeFormat('en-US', optsDate).format(d);
    
    // Custom fix for Y-m-d since en-US usually defaults to m/d/Y even with numeric options
    if (df === 'Y-m-d') {
        const parts = new Intl.DateTimeFormat('en-US', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(d);
        const y = parts.find(p => p.type === 'year')?.value;
        const m = parts.find(p => p.type === 'month')?.value;
        const dd = parts.find(p => p.type === 'day')?.value;
        datePart = `${y}-${m}-${dd}`;
    } else if (df === 'd/m/Y') {
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(d);
        const y = parts.find(p => p.type === 'year')?.value;
        const m = parts.find(p => p.type === 'month')?.value;
        const dd = parts.find(p => p.type === 'day')?.value;
        datePart = `${dd}/${m}/${y}`;
    }
    
    if (isDateOnly || !tf) return datePart;

    const optsTime = { timeZone: tz };
    if (tf === 'H:i') {
        optsTime.hour = '2-digit'; optsTime.minute = '2-digit'; optsTime.hour12 = false;
    } else { // g:i A
        optsTime.hour = 'numeric'; optsTime.minute = '2-digit'; optsTime.hour12 = true;
    }
    
    const timePart = new Intl.DateTimeFormat('en-US', optsTime).format(d);
    return `${datePart} ${timePart}`;
}
