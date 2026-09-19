<template>
  <div class="product-form">
    <admin-page-header :title="isEdit ? 'Edit product' : 'Add product'" :subtitle="isEdit ? item.name : 'Fill in the details, then add stock once it is saved.'">
      <el-button @click="$emit('cancel')">
        <el-icon><IconArrowLeft /></el-icon>
        Back to products
      </el-button>
      <el-button type="primary" :loading="saving" @click="submit">
        <el-icon><IconCheck /></el-icon>
        {{ isEdit ? 'Save changes' : 'Create product' }}
      </el-button>
    </admin-page-header>

    <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="submit">
      <div class="product-form__grid">
        <div class="stack">
          <admin-card title="Details">
            <el-form-item label="Category" prop="category_id">
              <el-select v-model="form.category_id" placeholder="Choose a category" filterable style="width: 100%">
                <el-option v-for="category in categories" :key="category.id" :value="category.id" :label="category.name" />
              </el-select>
            </el-form-item>
            <el-form-item label="Product name" prop="name">
              <el-input v-model="form.name" placeholder="e.g. Kids Painting Book" maxlength="190" />
            </el-form-item>
            <el-form-item label="Description" prop="description">
              <el-input v-model="form.description" type="textarea" :rows="4" placeholder="A short description shown on the product page" maxlength="10000" />
            </el-form-item>
          </admin-card>

          <admin-card title="Photos" :subtitle="`Up to ${MAX_IMAGES} photos · JPG or PNG · 5 MB each`">
            <div class="photos">
              <div v-for="image in savedImages" :key="image.id" class="photos__item">
                <img :src="image.thumbnail || image.link" alt="" @error="onImageError">
                <button type="button" class="photos__remove" aria-label="Remove photo" @click="removeSaved(image)">
                  <el-icon><IconDelete /></el-icon>
                </button>
              </div>

              <el-upload
                v-if="slotsLeft > 0"
                class="photos__upload"
                action="/api/upload-file"
                :headers="uploadHeaders"
                :show-file-list="true"
                list-type="picture-card"
                :limit="slotsLeft"
                :before-upload="checkImage"
                :on-success="onUploaded"
                :on-remove="onRemoveUploaded"
                :on-error="onUploadError"
                :on-exceed="onExceed"
                accept="image/jpeg,image/png"
                multiple
              >
                <el-icon><IconPlus /></el-icon>
              </el-upload>
            </div>
          </admin-card>
        </div>

        <div class="stack">
          <admin-card title="Pricing">
            <el-form-item label="Price per item" prop="amount">
              <el-input v-model="form.amount" placeholder="0.00" inputmode="decimal">
                <template #prepend>₦</template>
              </el-input>
            </el-form-item>
          </admin-card>

          <admin-card title="Bulk discounts" subtitle="Lower the unit price when a customer buys at least this many.">
            <div v-if="form.discounts.length" class="tiers">
              <div class="tiers__head">
                <span>Minimum quantity</span>
                <span>Price per item</span>
                <span />
              </div>
              <div v-for="(tier, index) in form.discounts" :key="tier.id || 'new-' + index" class="tiers__row">
                <el-input-number v-model="tier.minimum_order_quantity" :min="2" :max="1000000" controls-position="right" placeholder="Qty" />
                <el-input v-model="tier.amount" placeholder="0.00" inputmode="decimal">
                  <template #prepend>₦</template>
                </el-input>
                <el-tooltip content="Remove this tier" placement="top">
                  <el-button circle size="small" type="danger" plain aria-label="Remove tier" @click="removeTier(index)">
                    <el-icon><IconDelete /></el-icon>
                  </el-button>
                </el-tooltip>
              </div>
            </div>
            <admin-empty v-else icon="Sell" title="No bulk discounts" description="Add a tier to reward larger orders." class="tiers__empty" />
            <el-button class="tiers__add" @click="addTier">
              <el-icon><IconPlus /></el-icon>
              Add discount tier
            </el-button>
          </admin-card>
        </div>
      </div>
    </el-form>
  </div>
</template>

<script>
import { getToken } from '@/utils/auth';
import { onImageError } from '@/utils/index';
import Resource from '@/api/resource';

const createProduct = new Resource('stock/general-items/store');
const updateProduct = new Resource('stock/general-items/update');
const MAX_IMAGES = 3;

export default {
  name: 'ProductForm',
  props: {
    categories: { type: Array, default: () => [] },
    // null = adding a new product
    item: { type: Object, default: null },
  },
  emits: ['saved', 'cancel'],
  data() {
    return {
      MAX_IMAGES,
      saving: false,
      form: this.blankForm(),
      // photos already stored for this product / ids of photos uploaded in this session
      savedImages: [],
      uploadedIds: {}, // upload uid -> media id
      deletedImages: [],
      deletedDiscounts: [],
      rules: {
        category_id: [{ required: true, message: 'Choose a category', trigger: 'change' }],
        name: [{ required: true, message: 'Enter the product name', trigger: 'blur' }],
        amount: [
          { required: true, message: 'Enter a price', trigger: 'blur' },
          {
            validator: (rule, value, callback) => {
              const n = Number(value);
              callback(value === '' || Number.isNaN(n) || n < 0 ? new Error('Enter a valid price') : undefined);
            },
            trigger: 'blur',
          },
        ],
      },
    };
  },
  computed: {
    isEdit() {
      return !!(this.item && this.item.id);
    },
    uploadHeaders() {
      return { Authorization: 'Bearer ' + getToken() };
    },
    slotsLeft() {
      return MAX_IMAGES - this.savedImages.length;
    },
  },
  created() {
    if (this.isEdit) {
      // a COPY: editing must never change the table row until it is saved
      const item = JSON.parse(JSON.stringify(this.item));
      this.form = {
        name: item.name || '',
        category_id: item.category_id || '',
        description: item.description || '',
        amount: item.price ? item.price.amount : '',
        discounts: (item.discounts || []).map(d => ({
          id: d.id,
          minimum_order_quantity: Number(d.minimum_order_quantity),
          amount: d.amount,
        })),
      };
      this.savedImages = item.media || [];
    }
  },
  methods: {
    onImageError,
    blankForm() {
      return { name: '', category_id: '', description: '', amount: '', discounts: [] };
    },
    addTier() {
      const last = this.form.discounts[this.form.discounts.length - 1];
      if (last && (!last.minimum_order_quantity || last.amount === '' || last.amount === null)) {
        this.$message({ message: 'Fill in the current tier before adding another', type: 'warning' });
        return;
      }
      this.form.discounts.push({ minimum_order_quantity: undefined, amount: '' });
    },
    removeTier(index) {
      const [removed] = this.form.discounts.splice(index, 1);
      if (removed && removed.id) {
        this.deletedDiscounts.push(removed.id);
      }
    },
    removeSaved(image) {
      this.$confirm('Remove this photo from the product?', 'Remove photo', { confirmButtonText: 'Remove', cancelButtonText: 'Keep', type: 'warning' })
        .then(() => {
          this.deletedImages.push(image.id);
          this.savedImages = this.savedImages.filter(i => i.id !== image.id);
        })
        .catch(() => {});
    },
    checkImage(file) {
      const okType = file.type === 'image/jpeg' || file.type === 'image/png';
      const okSize = file.size / 1024 / 1024 <= 5;
      if (!okType) {
        this.$message.error('Photos must be JPG or PNG');
      } else if (!okSize) {
        this.$message.error('Each photo must be 5 MB or smaller');
      }
      return okType && okSize;
    },
    onUploaded(response, file) {
      this.uploadedIds[file.uid] = response.media_id;
    },
    // a photo uploaded but then removed before saving is simply not attached
    onRemoveUploaded(file) {
      delete this.uploadedIds[file.uid];
    },
    onUploadError() {
      this.$message.error('The photo could not be uploaded');
    },
    onExceed() {
      this.$message.warning(`A product can have at most ${MAX_IMAGES} photos`);
    },
    submit() {
      this.$refs.formRef.validate(valid => {
        if (!valid) {
          return;
        }
        // only what the API needs — not the whole product graph
        const payload = {
          name: this.form.name.trim(),
          category_id: this.form.category_id,
          description: this.form.description,
          amount: this.form.amount,
          images: Object.values(this.uploadedIds),
          deletedImages: this.deletedImages,
          discounts: this.form.discounts.filter(d => d.minimum_order_quantity && d.amount !== '' && d.amount !== null),
          deletedDiscounts: this.deletedDiscounts,
        };
        this.saving = true;
        const request = this.isEdit ? updateProduct.update(this.item.id, payload) : createProduct.store(payload);
        request
          .then(() => {
            this.$message({ message: this.isEdit ? 'Product updated' : 'Product created', type: 'success' });
            this.$emit('saved');
          })
          .catch(() => {
            // the shared axios interceptor has already shown the reason (e.g. a duplicate name)
          })
          .finally(() => {
            this.saving = false;
          });
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.product-form {
  &__grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    gap: 20px;
    align-items: start;

    .admin-card { margin-bottom: 0; }
  }
}

.photos {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;

  &__item {
    position: relative;
    width: 148px;
    height: 148px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--admin-border);

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__remove {
    position: absolute;
    top: 6px;
    right: 6px;
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: 50%;
    background: rgba(20, 28, 74, 0.75);
    color: #fff;
    cursor: pointer;
    transition: background-color 0.15s ease;

    &:hover { background: var(--admin-danger); }
  }

  &__upload {
    :deep(.el-upload--picture-card),
    :deep(.el-upload-list__item) {
      width: 148px;
      height: 148px;
      border-radius: 12px;
    }
  }
}

.tiers {
  &__head,
  &__row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 36px;
    gap: 10px;
    align-items: center;
  }

  &__head {
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--admin-muted);
  }

  &__row {
    margin-bottom: 10px;

    :deep(.el-input-number) { width: 100%; }
  }

  &__empty { padding: 20px 0 14px; }

  &__add { margin-top: 6px; }
}

@media (max-width: 1100px) {
  .product-form__grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
