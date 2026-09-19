import request from '@/utils/request';
import { ElLoading } from 'element-plus';
/**
 * Simple RESTful resource class
 */
class Resource {
  constructor(uri) {
    this.uri = uri;
  }
  list(query) {
    return request({
      url: '/' + this.uri,
      method: 'get',
      params: query,
    });
  }
  get(id) {
    return request({
      url: '/' + this.uri + '/' + id,
      method: 'get',
    });
  }
  store(resource) {
    return request({
      url: '/' + this.uri,
      method: 'post',
      data: resource,
    });
  }
  update(id, resource) {
    return request({
      url: '/' + this.uri + '/' + id,
      method: 'put',
      data: resource,
    });
  }
  destroy(id) {
    return request({
      url: '/' + this.uri + '/' + id,
      method: 'delete',
    });
  }
  loaderShow() {
    // Keeps the .hide() call shape every consumer already uses, so this is
    // the only place that needed to change when vue-loading-overlay (a
    // Vue 2-only global-static plugin) was replaced by Element Plus's
    // loading service.
    const instance = ElLoading.service({});
    return { hide: () => instance.close() };
  }
}

export { Resource as default };
