<template>
  <div class="categories">
    <admin-page-header title="Categories" subtitle="Group products so customers can browse them.">
      <el-button type="primary" @click="openDialog()">
        <el-icon><IconPlus /></el-icon>
        Add category
      </el-button>
    </admin-page-header>

    <admin-card flush>
      <template #toolbar>
        <div class="admin-toolbar">
          <el-input v-model="filter" class="grow" placeholder="Filter categories" clearable>
            <template #prefix>
              <el-icon><IconSearch /></el-icon>
            </template>
          </el-input>
        </div>
      </template>

      <el-table v-loading="loading" :data="visible" empty-text="No categories yet" :default-sort="{ prop: 'name', order: 'ascending' }">
        <el-table-column label="Name" prop="name" sortable min-width="240">
          <template #default="{ row }">
            <span class="cell-title">{{ row.name }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Products" min-width="140" align="right" sortable :sort-by="row => productCount(row)">
          <template #default="{ row }">
            <admin-status-tag :tone="productCount(row) ? 'info' : 'neutral'" :label="String(productCount(row))" kind="generic" />
          </template>
        </el-table-column>
        <el-table-column label="" width="130" align="right">
          <template #default="{ row }">
            <div class="row-actions">
              <el-tooltip content="Rename" placement="top">
                <el-button circle size="small" aria-label="Rename category" @click="openDialog(row)">
                  <el-icon><IconEdit /></el-icon>
                </el-button>
              </el-tooltip>
              <el-tooltip :content="productCount(row) ? 'Move or delete its products first' : 'Delete category'" placement="top">
                <span>
                  <el-button
                    circle
                    size="small"
                    type="danger"
                    plain
                    :disabled="productCount(row) > 0"
                    aria-label="Delete category"
                    @click="remove(row)"
                  >
                    <el-icon><IconDelete /></el-icon>
                  </el-button>
                </span>
              </el-tooltip>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </admin-card>

    <el-dialog v-model="dialogOpen" :title="editing ? 'Rename category' : 'Add category'" width="440px" @closed="reset">
      <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="save">
        <el-form-item label="Category name" prop="name">
          <el-input v-model="form.name" placeholder="e.g. Footwear" maxlength="120" @keyup.enter="save" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogOpen = false">Cancel</el-button>
        <el-button type="primary" :loading="saving" @click="save">{{ editing ? 'Save' : 'Add category' }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import Resource from '@/api/resource';

const categoriesResource = new Resource('stock/item-category');
const createResource = new Resource('stock/item-category/store');
const updateResource = new Resource('stock/item-category/update');
const deleteResource = new Resource('stock/item-category/delete');

export default {
  name: 'ItemCategory',
  data() {
    return {
      categories: [],
      loading: false,
      filter: '',
      dialogOpen: false,
      editing: null,
      saving: false,
      form: { name: '' },
      rules: {
        name: [{ required: true, message: 'Enter a category name', trigger: 'blur' }],
      },
    };
  },
  computed: {
    visible() {
      const needle = this.filter.trim().toLowerCase();
      return needle ? this.categories.filter(c => c.name.toLowerCase().includes(needle)) : this.categories;
    },
  },
  created() {
    this.fetchCategories();
  },
  methods: {
    productCount(category) {
      return (category.items || []).length;
    },
    fetchCategories() {
      this.loading = true;
      categoriesResource.list()
        .then(response => {
          this.categories = response.categories || [];
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
    openDialog(category = null) {
      this.editing = category;
      this.form.name = category ? category.name : '';
      this.dialogOpen = true;
    },
    reset() {
      this.editing = null;
      this.form.name = '';
      if (this.$refs.formRef) {
        this.$refs.formRef.clearValidate();
      }
    },
    save() {
      this.$refs.formRef.validate(valid => {
        if (!valid) {
          return;
        }
        this.saving = true;
        const request = this.editing
          ? updateResource.update(this.editing.id, { name: this.form.name.trim() })
          : createResource.store({ name: this.form.name.trim() });
        request
          .then(response => {
            // "store" answers 200 + a message when the name already exists
            if (response && response.message === 'Duplicate Name') {
              this.$message({ message: 'A category with that name already exists', type: 'warning' });
              return;
            }
            this.$message({ message: this.editing ? 'Category renamed' : 'Category added', type: 'success' });
            this.dialogOpen = false;
            this.fetchCategories();
          })
          .catch(() => {
            // the shared axios interceptor has already shown the reason
          })
          .finally(() => {
            this.saving = false;
          });
      });
    },
    remove(category) {
      this.$confirm(`Delete "${category.name}"? This cannot be undone.`, 'Delete category', {
        confirmButtonText: 'Delete',
        cancelButtonText: 'Keep it',
        type: 'warning',
      })
        .then(() => deleteResource.destroy(category.id))
        .then(() => {
          this.$message({ message: 'Category deleted', type: 'success' });
          this.fetchCategories();
        })
        .catch(() => {
          // dialog dismissed, or the interceptor already showed the reason
        });
    },
  },
};
</script>
