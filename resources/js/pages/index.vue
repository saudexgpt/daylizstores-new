<template>
  <div class="home">
    <section class="home__hero">
      <el-carousel :interval="7000" indicator-position="outside" height="520px" arrow="hover">
        <el-carousel-item v-for="(item, index) in items" :key="index">
          <div class="home__hero-slide">
            <div class="home__hero-copy">
              <p class="home__hero-eyebrow">New Collection</p>
              <h1 class="home__hero-title">{{ item.title }}</h1>
              <p class="home__hero-description">{{ item.description }}</p>
              <BaseButton variant="accent" @click="loadPage('/product/list')">Shop the Collection</BaseButton>
            </div>
            <div class="home__hero-media">
              <img :src="item.url" :alt="item.title" loading="lazy">
            </div>
          </div>
        </el-carousel-item>
      </el-carousel>
    </section>

    <!-- <section v-reveal class="home__section">
      <div class="home__section-heading">
        <SectionHeader title="Shop by Category" />
        <router-link to="/product/list" class="home__view-all">View all categories <el-icon><ArrowRight /></el-icon></router-link>
      </div>
      <div class="home__category-grid">
        <router-link
          v-for="category in categories"
          :key="category.id"
          class="home__category-tile"
          :to="{ name: 'CategorizedItems', params: { categoryId: category.id } }"
        >
          <span class="home__category-icon">
            <el-icon :size="26"><component :is="categoryIcon(category.name)" /></el-icon>
          </span>
          <span class="home__category-name">{{ category.name }}</span>
        </router-link>
      </div>
    </section> -->

    <section v-reveal class="home__section">
      <div class="home__section-heading">
        <SectionHeader title="Popular Products" />
        <router-link to="/product/list" class="home__view-all">View all products <el-icon><ArrowRight /></el-icon></router-link>
      </div>
      <item-menu :category-id="categoryId" />
    </section>
  </div>
</template>
<script>
import ItemMenu from './Menu';
import BaseButton from '@/components/ui/BaseButton.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import {
  ArrowRight, House, Bowl, Suitcase, Present, Football,
  MagicStick, Male, Female, Cellphone, Watch, Goods,
} from '@element-plus/icons-vue';
import { useItemsStore } from '@/store';

const CATEGORY_ICONS = [
  [/household/i, House],
  [/kitchen/i, Bowl],
  [/bag/i, Suitcase],
  [/kid/i, Present],
  [/sneaker|sandal|slipper/i, Football],
  [/health|beauty/i, MagicStick],
  [/men/i, Male],
  [/women|ladies/i, Female],
  [/gadget/i, Cellphone],
  [/watch/i, Watch],
];

export default {
  name: 'Home',
  components: {
    ItemMenu,
    BaseButton,
    SectionHeader,
    ArrowRight,
  },
  data() {
    return {
      categoryId: null,
      searchString: '',
      items: [
        { url: '/images/slider1.png', title: 'Household Items', description: 'Get your quality household items at affordable prices' },
        { url: '/images/slider2.png', title: 'Souvenirs', description: 'Varieties of gift items and souvenirs at affordable prices' },
        { url: '/images/slider6.png', title: 'Kiddies', description: 'Varieties of quality products for kids' },
        { url: '/images/slider4.png', title: 'Men\'s Fashion', description: 'Quality products for men at affordable prices' },
        { url: '/images/slider5.png', title: 'Ladies\' Fashion', description: 'Quality products for ladies at affordable prices' },
        { url: '/images/slider3.png', title: 'Buy Online', description: 'You are just a click away from grabbing your desired product' },
      ],
    };
  },
  computed: {
    itemsStore() {
      return useItemsStore();
    },
    categories() {
      return this.itemsStore.categories;
    },
  },
  methods: {
    loadPage(path) {
      this.$router.push({ path });
    },
    categoryIcon(name) {
      const match = CATEGORY_ICONS.find(([pattern]) => pattern.test(name));
      return match ? match[1] : Goods;
    },
  },
};
</script>
<style lang="scss" scoped>
.home {
  

  &__hero {
    // A concrete pixel height here (rather than a % or vh-based one) is
    // deliberate: el-carousel's internal item/container divs are
    // position:absolute (needed for the slide-transition animation) and
    // don't reliably resolve percentage heights up through this component's
    // own wrapper, which previously left dead space below the carousel.
    :deep(.el-carousel),
    :deep(.el-carousel__container),
    :deep(.el-carousel__item) {
      height: 520px !important;
    }

    :deep(.el-carousel__indicators--outside) {
      margin-top: 12px;
    }

    @media (max-width: 700px) {
      :deep(.el-carousel),
      :deep(.el-carousel__container),
      :deep(.el-carousel__item) {
        height: 560px !important;
      }
    }
  }

  &__hero-slide {
    display: grid;
    grid-template-columns: 1fr 1fr;
    height: 100%;

    // Grid items default to min-width:auto, so without this a slide image's
    // intrinsic pixel size can force the whole row (and page) wider than the
    // viewport instead of shrinking to the 1fr track.
    > * {
      min-width: 0;
    }

    @media (max-width: 700px) {
      grid-template-columns: 1fr;
      height: auto;
    }
  }

  &__hero-copy {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 0 64px;
    background: var(--color-navy-light);

    @media (max-width: 700px) {
      padding: 48px 28px;
      order: 2;
    }
  }

  &__hero-media {
    overflow: hidden;

    @media (max-width: 700px) {
      order: 1;
      height: 260px;
    }

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
  }

  &__hero-eyebrow {
    font-family: var(--font-sans);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--color-accent);
    margin: 0 0 16px;
  }

  &__hero-title {
    font-family: var(--font-serif);
    font-weight: 600;
    font-size: clamp(32px, 4vw, 48px);
    line-height: 1.15;
    color: var(--color-navy);
    margin: 0 0 18px;
    max-width: 420px;
  }

  &__hero-description {
    font-family: var(--font-sans);
    font-size: 15px;
    line-height: 1.6;
    color: var(--color-text);
    margin: 0 0 32px;
    max-width: 380px;
  }

  &__section {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: var(--space-section) 32px;

    @media (max-width: 900px) {
      padding: 56px 20px;
    }
  }

  &__categories {
    background: var(--color-navy-light);
    padding: 40px 0;
  }

  &__section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 32px;

    :deep(.section-header) {
      margin-bottom: 0;
    }
  }

  &__view-all {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 600;
    color: var(--color-accent);
    text-decoration: none;
    white-space: nowrap;

    &:hover {
      text-decoration: underline;
    }
  }

  &__category-grid {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: 0 32px;
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 24px 16px;

    @media (max-width: 900px) {
      grid-template-columns: repeat(3, 1fr);
      padding: 0 20px;
    }

    @media (max-width: 560px) {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  &__category-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    text-align: center;
  }

  &__category-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: var(--color-surface);
    color: var(--color-navy);
    border: 1px solid var(--color-border);
    transition: background-color 0.2s ease, border-color 0.2s ease;
  }

  &__category-tile:hover &__category-icon {
    background: var(--color-navy);
    color: #fff;
    border-color: var(--color-navy);
  }

  &__category-name {
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 500;
    color: var(--color-navy);
  }
}
</style>
