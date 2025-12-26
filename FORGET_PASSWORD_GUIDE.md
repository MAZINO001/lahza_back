## Forget Password Implementation Guide

Your Laravel backend is now configured with a complete forget password system using Breeze and Sanctum. Here's how to integrate it with your React frontend.

### Backend Setup (Already Completed)

1. **ResetPasswordNotification** - Custom notification that sends password reset links
2. **PasswordResetLinkController** - Handles forgot password requests (`/api/forgot-password`)
3. **NewPasswordController** - Handles password reset submissions (`/api/reset-password`)
4. **User Model** - Updated to send custom password reset notifications
5. **API Routes** - Public endpoints added for password reset flow

### Environment Configuration

Add this to your `.env` file to set your React frontend URL:

```env
FRONTEND_URL=http://localhost:5173
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-password
MAIL_FROM_NAME="Your App Name"
```

### API Endpoints

#### 1. Request Password Reset Link
**POST** `/api/forgot-password`

Request body:
```json
{
  "email": "user@example.com"
}
```

Response (success):
```json
{
  "status": "passwords.sent"
}
```

Response (error):
```json
{
  "message": "...",
  "errors": {
    "email": ["User not found"]
  }
}
```

#### 2. Reset Password
**POST** `/api/reset-password`

Request body:
```json
{
  "token": "reset-token-from-email",
  "email": "user@example.com",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

Response (success):
```json
{
  "status": "passwords.reset"
}
```

### React Frontend Integration

#### Step 1: Create Forgot Password Page

```jsx
import { useState } from 'react';
import axios from 'axios';

const ForgotPasswordPage = () => {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setMessage('');

    try {
      const response = await axios.post(
        'http://localhost:8000/api/forgot-password',
        { email },
        {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          withCredentials: true,
        }
      );

      setMessage('Password reset link sent! Check your email.');
      setEmail('');
    } catch (err) {
      if (err.response?.data?.errors?.email) {
        setError(err.response.data.errors.email[0]);
      } else {
        setError('Failed to send reset link. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="forgot-password-container">
      <h2>Forgot Password?</h2>
      <p>Enter your email to receive password reset instructions.</p>
      
      <form onSubmit={handleSubmit}>
        <input
          type="email"
          placeholder="Enter your email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
        />
        <button type="submit" disabled={loading}>
          {loading ? 'Sending...' : 'Send Reset Link'}
        </button>
      </form>

      {message && <p className="success">{message}</p>}
      {error && <p className="error">{error}</p>}
    </div>
  );
};

export default ForgotPasswordPage;
```

#### Step 2: Create Reset Password Page

```jsx
import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import axios from 'axios';

const ResetPasswordPage = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const token = searchParams.get('token');
  const email = searchParams.get('email');

  useEffect(() => {
    if (!token || !email) {
      setError('Invalid or missing reset link');
    }
  }, [token, email]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setMessage('');

    if (password !== passwordConfirmation) {
      setError('Passwords do not match');
      setLoading(false);
      return;
    }

    if (password.length < 8) {
      setError('Password must be at least 8 characters');
      setLoading(false);
      return;
    }

    try {
      const response = await axios.post(
        'http://localhost:8000/api/reset-password',
        {
          token,
          email,
          password,
          password_confirmation: passwordConfirmation,
        },
        {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          withCredentials: true,
        }
      );

      setMessage('Password reset successful! Redirecting to login...');
      setTimeout(() => {
        navigate('/login');
      }, 2000);
    } catch (err) {
      if (err.response?.data?.errors) {
        const errors = err.response.data.errors;
        const firstError = Object.values(errors)[0]?.[0];
        setError(firstError || 'Failed to reset password');
      } else {
        setError(err.response?.data?.message || 'Failed to reset password');
      }
    } finally {
      setLoading(false);
    }
  };

  if (!token || !email) {
    return <div className="error">Invalid reset link</div>;
  }

  return (
    <div className="reset-password-container">
      <h2>Reset Your Password</h2>
      
      <form onSubmit={handleSubmit}>
        <input
          type="password"
          placeholder="New Password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
          minLength="8"
        />
        
        <input
          type="password"
          placeholder="Confirm Password"
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          required
          minLength="8"
        />
        
        <button type="submit" disabled={loading}>
          {loading ? 'Resetting...' : 'Reset Password'}
        </button>
      </form>

      {message && <p className="success">{message}</p>}
      {error && <p className="error">{error}</p>}
    </div>
  );
};

export default ResetPasswordPage;
```

#### Step 3: Update Your Routes

Add these routes to your React Router setup:

```jsx
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';

const routes = [
  // ... other routes
  {
    path: '/forgot-password',
    element: <ForgotPasswordPage />,
  },
  {
    path: '/reset-password',
    element: <ResetPasswordPage />,
  },
];
```

#### Step 4: Add Link in Login Page

```jsx
<a href="/forgot-password">Forgot your password?</a>
```

### Email Customization

To customize the password reset email, edit [ResetPasswordNotification.php](app/Notifications/ResetPasswordNotification.php).

You can modify:
- Email subject
- Greeting message
- Body text
- Action button text
- Footer text

Example customization:

```php
return (new MailMessage)
    ->subject('Reset Your Password - Your App Name')
    ->greeting('Hi ' . $notifiable->name . '!')
    ->line('Click the button below to reset your password. This link expires in 60 minutes.')
    ->action('Reset Password', $resetUrl)
    ->line('If you did not request this, please ignore this email.')
    ->salutation('Warm regards,\nYour App Team');
```

### Security Considerations

1. **HTTPS in Production**: Always use HTTPS URLs in production
2. **Token Expiration**: Default is 60 minutes (configurable in `config/auth.php`)
3. **Rate Limiting**: Consider adding rate limiting to prevent abuse
4. **CORS**: Already configured in `config/cors.php` for your local React port

### Troubleshooting

#### Email Not Sending
- Check `.env` mail configuration
- Verify `FRONTEND_URL` is set correctly
- Check your mail service logs

#### Reset Link Invalid
- Ensure token hasn't expired (60 minutes default)
- Verify email matches the token
- Check database `password_reset_tokens` table

#### CORS Error
- Update `allowed_origins` in `config/cors.php` if needed
- Ensure `withCredentials: true` in API calls

### Testing

You can test the API endpoints using cURL:

```bash
# Request reset link
curl -X POST http://localhost:8000/api/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'

# Reset password (replace token and email)
curl -X POST http://localhost:8000/api/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "token":"your-token",
    "email":"user@example.com",
    "password":"newpassword123",
    "password_confirmation":"newpassword123"
  }'
```

### Production Deployment

Before deploying to production:

1. Update `.env` with production mail configuration
2. Set `APP_ENV=production`
3. Set `FRONTEND_URL` to your production React URL
4. Update CORS `allowed_origins` to include production domain
5. Run migrations if you haven't already: `php artisan migrate`
6. Consider enabling Laravel Telescope for debugging (already installed)

That's it! Your forget password functionality is ready for use.
