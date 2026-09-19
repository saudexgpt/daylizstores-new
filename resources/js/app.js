import '@fortawesome/fontawesome-free/css/all.min.css';
import './styles/element-variables.scss';
import { createApp } from 'vue';
import Cookies from 'js-cookie';
import ElementPlus, { ElMessage, ElMessageBox, ElNotification } from 'element-plus';
import App from './views/App';
import router from '@/router';
import { pinia } from '@/store';
import i18n, { getElementLocale, getLanguage } from './lang'; // Internationalization
import installIcons from '@/icons'; // icon
import { installAdminIcons } from '@/utils/icons';
import { installAdminComponents } from '@/components/admin';
import '@/permission'; // permission control
import reveal from '@/directive/reveal';

const app = createApp(App);
app.directive('reveal', reveal);

app.use(router);
app.use(pinia);
app.use(i18n);
app.use(ElementPlus, {
  size: Cookies.get('size') || 'default', // set element-plus default size
  locale: getElementLocale(getLanguage()),
});
installIcons(app);
installAdminIcons(app); // <IconEdit />, <IconDelete /> ... (Element Plus SVG icons)
installAdminComponents(app); // <AdminPageHeader>, <AdminCard>, <AdminStatCard> ...

// element-plus doesn't auto-inject $message/$confirm/$alert/$prompt/$notify
// onto every component instance the way element-ui did; this shim keeps the
// ~30 existing call sites across the app unchanged.
app.config.globalProperties.$message = ElMessage;
app.config.globalProperties.$notify = ElNotification;
app.config.globalProperties.$confirm = ElMessageBox.confirm;
app.config.globalProperties.$alert = ElMessageBox.alert;
app.config.globalProperties.$prompt = ElMessageBox.prompt;

app.mount('#app');
