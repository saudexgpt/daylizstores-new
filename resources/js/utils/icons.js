/**
 * Admin icon registry.
 *
 * Element UI's `<i class="el-icon-edit">` font icons no longer exist in Element
 * Plus (they render as nothing), so every admin icon is now an SVG component
 * from @element-plus/icons-vue. Only the icons the admin actually uses are
 * imported (tree-shaken), and each is registered globally as `Icon<Name>` so
 * any template can write `<IconEdit />` — the prefix keeps them from clashing
 * with local components that happen to share a name (`Menu`, `Link`, `Search`).
 *
 * Choose icons by PURPOSE, not by look-alike:
 *   navigation   DataBoard (dashboard) · Goods (products) · ShoppingBag (orders) · Setting
 *   people       User (customer) · UserFilled (staff account) · Key (roles & permissions)
 *   records      Memo (audit trail) · Bell (notifications) · Files (backup)
 *   row actions  View · Edit · Delete · Printer · Download · Upload
 *   status       Check · Close · Warning · Clock · Van (in transit) · Money (payment)
 */
import {
  Avatar,
  Bell,
  Box,
  Calendar,
  Check,
  CircleCheck,
  CircleClose,
  Clock,
  Close,
  Coin,
  CollectionTag,
  CopyDocument,
  DataBoard,
  Delete,
  Document,
  Download,
  Edit,
  Files,
  FullScreen,
  Goods,
  HomeFilled,
  InfoFilled,
  Iphone,
  Key,
  List,
  Location,
  Lock,
  Memo,
  Message,
  Money,
  MoreFilled,
  Operation,
  Picture,
  Plus,
  Printer,
  Refresh,
  RefreshRight,
  Remove,
  Search,
  Setting,
  ShoppingBag,
  ShoppingCart,
  SwitchButton,
  Tickets,
  TopRight,
  TrendCharts,
  Upload,
  User,
  UserFilled,
  Van,
  View,
  Warning,
  WarningFilled,
  ArrowDown,
  ArrowLeft,
  ArrowRight,
  CaretBottom,
  Fold,
  Expand,
  Rank,
  Star,
  Sell,
  Wallet,
  Notebook,
  PieChart,
  Histogram,
  DataAnalysis,
  DataLine,
  Switch,
  Filter,
  Unlock,
  DocumentAdd,
  ArrowUp,
  CirclePlus,
  Finished,
} from '@element-plus/icons-vue';

export const adminIcons = {
  Avatar, Bell, Box, Calendar, Check, CircleCheck, CircleClose, Clock, Close, Coin, CollectionTag,
  CopyDocument, DataBoard, Delete, Document, Download, Edit, Files, FullScreen, Goods, HomeFilled,
  InfoFilled, Iphone, Key, List, Location, Lock, Memo, Message, Money, MoreFilled, Operation,
  Picture, Plus, Printer, Refresh, RefreshRight, Remove, Search, Setting, ShoppingBag, ShoppingCart,
  SwitchButton, Tickets, TopRight, TrendCharts, Upload, User, UserFilled, Van, View, Warning,
  WarningFilled, ArrowDown, ArrowLeft, ArrowRight, CaretBottom, Fold, Expand, Rank, Star, Sell,
  Wallet, Notebook, PieChart, Histogram, DataAnalysis, DataLine, Switch, Filter, Unlock, DocumentAdd,
  ArrowUp, CirclePlus, Finished,
};

/** Registers every admin icon globally as `Icon<Name>`. */
export function installAdminIcons(app) {
  Object.entries(adminIcons).forEach(([name, component]) => {
    app.component('Icon' + name, component);
  });
}

/**
 * Legacy names -> the icon that fits the PURPOSE. Route metadata and a few
 * older templates still carry Element UI class names (`el-icon-video-camera`
 * was being used for the audit trail!) or the old SVG-sprite names.
 */
const LEGACY = {
  'el-icon-s-home': 'DataBoard', // dashboard
  'el-icon-menu': 'Goods', // manage products
  'el-icon-shopping-cart-full': 'ShoppingBag', // customer orders
  'el-icon-shopping-cart-2': 'ShoppingCart',
  'el-icon-setting': 'Setting', // administrator
  'el-icon-user': 'User', // customers
  'el-icon-s-check': 'Key', // roles & permissions
  'el-icon-video-camera': 'Memo', // audit trail (was a video camera)
  'el-icon-bell': 'Bell',
  'el-icon-download': 'Download',
  'el-icon-upload': 'Upload',
  'el-icon-document': 'Document',
  'el-icon-tickets': 'Tickets',
  'el-icon-plus': 'Plus',
  'el-icon-close': 'Close',
  'el-icon-check': 'Check',
  'el-icon-search': 'Search',
  'el-icon-edit': 'Edit',
  'el-icon-delete': 'Delete',
  'el-icon-view': 'View',
  'el-icon-printer': 'Printer',
  'el-icon-truck': 'Van',
  'el-icon-money': 'Money',
  'el-icon-coin': 'Coin',
  'el-icon-location': 'Location',
  'el-icon-message': 'Message',
  'el-icon-mobile-phone': 'Iphone',
  'el-icon-key': 'Key',
  'el-icon-date': 'Calendar',
  'el-icon-more-outline': 'MoreFilled',
  'el-icon-s-order': 'List',
  'el-icon-caret-bottom': 'CaretBottom',
  user: 'User',
  '404': 'Warning',
};

/**
 * Turn whatever a route's `meta.icon` holds (a legacy class name, a legacy
 * sprite name, or already an icon name like `DataBoard`) into the name of a
 * registered icon component, or '' when there isn't one.
 */
export function resolveIconName(name) {
  if (!name) {
    return '';
  }
  const bare = LEGACY[name] || name;
  return adminIcons[bare] ? 'Icon' + bare : '';
}
