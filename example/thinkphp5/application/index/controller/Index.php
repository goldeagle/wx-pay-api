<?php
namespace app\index\controller;

use app\index\model\Order;
use think\Controller;
use wxpay\database\WxPayUnifiedOrder;
use wxpay\JsApiPay;
use wxpay\NativePay;
use wxpay\PayNotifyCallBack;
use think\Log;
use wxpay\WxPayApi;
use wxpay\WxPayConfig;

/**
 * 样例控制器
 *
 * Class Index
 * @package app\index\controller
 * @author goldeagle
 */
class Index extends Controller
{
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 异步接收订单返回信息，订单成功付款后，处理订单状态并批量生成用户的二维码
     * @param int $id 订单编号
     */
    public function notify($id = 0)
    {
        $notify = new PayNotifyCallBack();
        $notify->handle(true);


        //找到匹配签名的订单
        $order = Order::get($id);
        if (!isset($order)) {
            Log::write('未找到订单，id= ' . $id);
        }
        $succeed = ($notify->getReturnCode() == 'SUCCESS') ? true : false;
        if ($succeed) {

            Log::write('订单' . $order->id . '生成二维码成功');

            $order->save(['flag' => '2'], ['id' => $order->id]);
            Log::write('订单' . $order->id . '状态更新成功');
        } else {
            Log::write('订单' . $id . '支付失败');
        }
    }

    /**
     * 使用微信支付SDK生成支付用的二维码
     * @param $id
     */
    public function wxpayQRCode($id)
    {
        $order = Order::get($id);
        if (!isset($order)) $this->error('查询不到正确的订单信息');

        //判断是否已经存在订单 url，如果已经存在且未超过2小时就使用旧的，否则生成新的
        $interval = date_diff(new \DateTime($order->update_time), new \DateTime());
        $h = $interval->format('%h');

        if (isset($order->pay_url) && $order->pay_url != '' && $h < 2) {
            $url = $order->pay_url;
        } else {
            $order->money = 0.01;
            $notify = new NativePay();
            $input = new WxPayUnifiedOrder();
            $input->setBody("支付 0.01 元");
            $input->setAttach("test");
            $input->setOutTradeNo(WxPayConfig::MCHID . date("YmdHis"));
            $input->setTotalFee($order->money);
            $input->setTimeStart(date("YmdHis"));
            $input->setTimeExpire(date("YmdHis", time() + 600));
            $input->setGoodsTag("QRCode");
            $input->setNotifyUrl("http://localhost/index/index/notify/id/" . $order->id);
            $input->setTradeType("NATIVE");
            $input->setProductId($id);
            $result = $notify->getPayUrl($input);
            $url = $result["code_url"];

            //保存订单标识
            $order->save();
        }

        //生成二维码
        return $this->getUrlQRCode($url);
    }

    /**
     * 根据给出的 url 地址生成 QRCode
     * @param $url
     */
    public static function getUrlQRCode($url)
    {
        $qrCode = new \Endroid\QrCode\QrCode();
        $qrCode->setText($url)
            ->setSize(300)
            ->setPadding(10)
            ->setErrorCorrection('high')
            ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
            ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
            ->setLabelFontSize(16)
            ->setImageType(\Endroid\QrCode\QrCode::IMAGE_TYPE_JPEG);
        $qrCode->render();
    }

    /**
     * 微信支付使用 JSAPI 的样例
     * @return mixed
     */
    public function wxpayJSAPI()
    {

        if (isset($id) && $id != 0) {
            //获取用户openid
            $tools = new JsApiPay();
            $openId = session('user_openid', '', 'index');

            //统一下单
            $money = 0.01;
            $input = new WxPayUnifiedOrder();
            $input->setBody("支付 0.01 元");
            $input->setAttach("test");
            $input->setOutTradeNo(WxPayConfig::MCHID . date("YmdHis"));
            $input->setTotalFee($money * 100);
            $input->setTimeStart(date("YmdHis"));
            $input->setTimeExpire(date("YmdHis", time() + 600));
            $input->setGoodsTag("Reward");
            $input->setNotifyUrl("http://localhost/index/index/notify/id/" . $id);
            $input->setTradeType("JSAPI");
            $input->setOpenid($openId);
            $order = WxPayApi::unifiedOrder($input);

            $jsApiParameters = $tools->getJsApiParameters($order);

            $this->assign('order', $order);
            $this->assign('jsApiParameters', $jsApiParameters);
            return $this->fetch('jsapi');
        }
    }
}
