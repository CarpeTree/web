# 📧 iCloud Custom Domain Email Troubleshooting

## 🔍 Your Current Setup:
- **SMTP Server**: smtp.mail.me.com (iCloud)
- **Authentication**: pherognome@icloud.com (Your Apple ID)
- **Sending From**: quotes@carpetree.com (Custom domain)
- **Problem**: Emails going to spam/junk

## ✅ Check iCloud Mail Settings:

### 1. Verify Custom Domain in iCloud
1. **Go to**: [icloud.com](https://icloud.com) → **Mail**
2. **Check**: Settings → Accounts
3. **Verify**: carpetree.com domain is properly configured
4. **Status**: Should show "Active" or "Verified"

### 2. Check Domain Authentication
1. **iCloud Console**: [icloud.com/mail](https://icloud.com/mail)
2. **Settings** → **Custom Email Domain**
3. **Verify**: DNS records are properly set up
4. **Look for**: Any warnings or errors

### 3. Apple ID App-Specific Password
1. **Go to**: [appleid.apple.com](https://appleid.apple.com)
2. **Sign-In & Security** → **App-Specific Passwords**
3. **Check**: Current password is working
4. **Generate new** if needed

## 🚨 Common iCloud Custom Domain Issues:

### Issue 1: Domain Not Fully Verified
- **Symptom**: Emails flagged as spam
- **Fix**: Re-verify domain in iCloud settings

### Issue 2: Sending Volume Too High
- **Symptom**: Emails blocked or delayed
- **Fix**: Implement sending limits (1 email every 10 seconds)

### Issue 3: Missing Authentication Records
- **Symptom**: Poor deliverability
- **Fix**: Ensure SPF/DKIM are set up in DNS

### Issue 4: From Address Mismatch
- **Symptom**: Spam classification
- **Fix**: Match "From" address to verified domain

## 🔧 Quick Fixes to Try:

### 1. Add Sending Delay
Add delay between emails to avoid rate limits

### 2. Improve Email Content
- More professional subject lines ✅ (Already done)
- Less automated-looking content ✅ (Already done)
- Proper HTML formatting ✅ (Already done)

### 3. Check DNS Records
Ensure these are set in your DNS:
- **SPF**: `v=spf1 include:icloud.com ~all`
- **MX**: Point to iCloud servers
- **CNAME**: For mail subdomain

## 🎯 Test Steps:

1. **Send test email** from iCloud web interface
2. **Check delivery** to your Gmail
3. **Compare headers** between web and automated emails
4. **Monitor spam scores**

## 📞 Apple Support:
If issues persist:
- **Call**: Apple Business Support
- **Topic**: "Custom domain email deliverability issues"
- **Mention**: "Transactional business emails going to spam" 