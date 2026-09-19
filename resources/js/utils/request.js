import axios from 'axios';
import { ElMessage } from 'element-plus';
import { getToken, setToken } from '@/utils/auth';

// Create axios instance
const service = axios.create({
  baseURL: import.meta.env.VITE_BASE_API,
  timeout: 60000, // Request timeout — was 300s, which just hid slow/hanging
  // requests from users instead of surfacing them; 60s is generous enough for
  // the slowest real operations here (receipt-image upload, bulk imports).
});

// Request intercepter
service.interceptors.request.use(
  config => {
    const token = getToken();
    if (token) {
      config.headers['Authorization'] = 'Bearer ' + getToken(); // Set JWT token
    }

    return config;
  },
  error => {
    // Do something with request error
    console.log(error); // for debug
    Promise.reject(error);
  },
);

// response pre-processing
service.interceptors.response.use(
  response => {
    if (response.headers.authorization) {
      setToken(response.headers.authorization);
      response.data.token = response.headers.authorization;
    }

    return response.data;
  },
  error => {
    // A failed file download (responseType 'blob') carries its JSON error body as a Blob;
    // read it first so the user sees the server's real message, not "Request failed with 422".
    const body = error.response && error.response.data;
    if (typeof Blob !== 'undefined' && body instanceof Blob && /json/.test(body.type)) {
      return body.text().then(text => {
        try {
          error.response.data = JSON.parse(text);
        } catch (e) {
          error.response.data = {};
        }
        return reportError(error);
      });
    }
    return reportError(error);
  },
);

function reportError(error) {
  let message = error.message;
  // A timeout or network failure never gets a `response` at all (unlike an
  // HTTP error status, which does) — reading `.data` on it unconditionally
  // would throw here instead of showing the user an error message.
  if (error.response && error.response.data && error.response.data.messages) {
    message = error.response.data.messages;
  } else if (error.response && error.response.data && error.response.data.message) {
    message = error.response.data.message;
  }
  console.log(message);
  ElMessage({
    message: message,
    type: 'error',
    duration: 10 * 1000,
  });
  return Promise.reject(error);
}

export default service;
