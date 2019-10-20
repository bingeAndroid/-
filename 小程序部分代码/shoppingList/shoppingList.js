var init = require('../../utils/init.js');
const app = getApp();
import ServerceApi from '../../utils/serverceApi.js';
import Http from '../../utils/http.js';
import until from "../../utils/util.js"
let _util = new until();
let _ServerceApi = new ServerceApi();
let _Http = new Http()
var newInit = Object.assign({}, init)

newInit.data = Object.assign({}, newInit.data, {
  imageurl: app.globalData.imageurl,
  hide_model_price:true,
  hide_model_sleect_country: true,
  hide_model_sleect_country1: true,
  floorstatus: false,

});
newInit = Object.assign({}, newInit, {
  // 获取滚动条当前位置
  onPageScroll: function (e) {
    console.log(e)
    if (e.scrollTop > 100) {
      this.setData({
        floorstatus: true
      });
    } else {
      this.setData({
        floorstatus: false
      });
    }
  },
  //回到顶部
  goTop(e) { // 一键回到顶部
    console.log(wx.pageScrollTo)
    if (wx.pageScrollTo) {
      wx.pageScrollTo({
        scrollTop: 0
      })
    } else {
      wx.showModal({
        title: '提示',
        content: '当前微信版本过低，无法使用该功能，请升级到最新微信版本后重试。'
      })
    }
  },


  getBranAndCountry(){
    _ServerceApi.getBrandList().then(res => {
      if (res.code == 0) {
        this.setData({
          BrandList: res.data
        })
      } else {
        _util.showToast('none', res.msg)

      }

    }, err => {
      _util.showToast('none', "发送失败")
    });

    _ServerceApi.getCountriesList().then(res => {
      if (res.code == 0) {
        this.setData({
          CountriesList: res.data
        })
      } else {
        _util.showToast('none', res.msg)

      }

    }, err => {
      _util.showToast('none', "发送失败")
    });

  },

  getIndexDate(fresh) {
    this.getBranAndCountry()
    wx.showLoading({ title: '加载中…' })

    var { page, lists, urlData } = this.data
    fresh && this.setData({ lists: [], page: 0, total_page: 0 })
    this.setData({
      page: fresh ? 1 : this.data.page + 1,

    })
    var fd_id = urlData.fid
    var order =this.data.order
    var page = this.data.page
    var brand_id = this.data.brand_id
    var countrie_id = this.data.countrie_id
    var low_price = this.data.minPrice
    var high_price = this.data.maxPrice

    _ServerceApi.getFullDownGoodsList(fd_id, order, page, brand_id, countrie_id, low_price, high_price).then(res => {  
      if (res.code == 0) {
        var { pages, list, goods_class, info } = res.data || {};
        this.setData({

          page: parseInt(pages.currPage) || 0,
          total_page: parseInt(pages.totalPage) || 0,
          lists: fresh ? list : this.data.lists.concat(list || []),
          info: info
        })
        //list && _util.requestStatus(this, '', 'done');
        setTimeout(function () {
          wx.hideLoading()
        }, 2000)
        if (this.data.lists.length==0) {
          list.length == 0 && _util.requestStatus(this, '', 'nodata');

        }else{
          this.setData({
            loadFlag:''
          })
        }
      } else {
        _util.showToast('none', res.msg);

      }
    }, err => {
      _util.showToast('none', "发送失败")
    });

  }

 
});
Page(newInit);
