<script>
import { h } from 'vue';

const MenuItem = (props) => {
  const { icon, title } = props;
  const vnodes = [];

  if (icon) {
    vnodes.push(h('i', { class: icon }));
  }

  if (title) {
    vnodes.push(h('span', title));
  }
  return vnodes;
};

MenuItem.props = {
  icon: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    default: '',
  },
};

export default MenuItem;
</script>
